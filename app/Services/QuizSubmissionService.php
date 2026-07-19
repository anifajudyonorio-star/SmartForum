<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizResult;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizSubmissionService
{
    public function startAttempt(User $user, Quiz $quiz): QuizAttempt|QuizResult
    {
        $this->ensureStudent($user);
        $this->ensureQuizAccess($user, $quiz);

        return DB::transaction(function () use ($user, $quiz) {
            $result = $this->lockedResult($quiz, $user);

            if ($result) {
                return $result;
            }

            if (in_array($quiz->lifecycleStatus(), [
                Quiz::STATUS_DRAFT,
                Quiz::STATUS_SCHEDULED,
            ], true)) {
                throw ValidationException::withMessages([
                    'quiz' => 'This quiz is not currently available.',
                ]);
            }

            $attempt = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($attempt) {
                $this->ensureSnapshotsExist($attempt, $quiz);

                if ($attempt->status !== QuizAttempt::STATUS_IN_PROGRESS
                    || $this->submissionWindowClosed($attempt, $quiz)) {
                    return $this->finalize($attempt, $quiz, autoSubmitted: true);
                }

                return $attempt;
            }

            $this->validateQuizAvailability($quiz);

            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'status' => QuizAttempt::STATUS_IN_PROGRESS,
            ]);

            $this->ensureSnapshotsExist($attempt, $quiz);

            return $attempt;
        }, 3);
    }

    /**
     * @param  array<int|string, mixed>  $submittedAnswers
     */
    public function submit(
        User $user,
        Quiz $quiz,
        int $attemptId,
        array $submittedAnswers
    ): QuizResult {
        $this->ensureStudent($user);
        $this->ensureQuizAccess($user, $quiz);

        return DB::transaction(function () use ($user, $quiz, $attemptId, $submittedAnswers) {
            $result = $this->lockedResult($quiz, $user);

            // A replay returns the immutable result produced by the first request.
            if ($result) {
                return $result;
            }

            if (in_array($quiz->lifecycleStatus(), [
                Quiz::STATUS_DRAFT,
                Quiz::STATUS_SCHEDULED,
            ], true)) {
                throw ValidationException::withMessages([
                    'quiz' => 'This quiz is not currently available.',
                ]);
            }

            $attempt = QuizAttempt::query()
                ->whereKey($attemptId)
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                throw ValidationException::withMessages([
                    'attempt_id' => 'A valid in-progress attempt is required.',
                ]);
            }

            $this->ensureSnapshotsExist($attempt, $quiz);

            if ($attempt->status !== QuizAttempt::STATUS_IN_PROGRESS) {
                return $this->finalize($attempt, $quiz, autoSubmitted: true);
            }

            // The deadline is exclusive: a request at the exact deadline is late.
            // Late request payloads are ignored; only answers saved before expiry
            // can contribute to the automatically finalized result.
            if ($this->submissionWindowClosed($attempt, $quiz)) {
                return $this->finalize($attempt, $quiz, autoSubmitted: true);
            }

            $this->applySubmittedAnswers($attempt, $submittedAnswers);

            return $this->finalize($attempt, $quiz, autoSubmitted: false);
        }, 3);
    }

    public function authoritativeDeadline(QuizAttempt $attempt, Quiz $quiz): CarbonInterface
    {
        $personalDeadline = $attempt->started_at->copy()->addMinutes((int) $quiz->duration);

        return $personalDeadline->lte($quiz->end_time)
            ? $personalDeadline
            : $quiz->end_time->copy();
    }

    public function remainingSeconds(QuizAttempt $attempt, Quiz $quiz): int
    {
        return max(0, $this->authoritativeDeadline($attempt, $quiz)->getTimestamp() - now()->getTimestamp());
    }

    private function ensureStudent(User $user): void
    {
        if (! $user->isStudent()) {
            throw new AuthorizationException('Only students can take quizzes.');
        }
    }

    private function ensureQuizAccess(User $user, Quiz $quiz): void
    {
        if (! $user->can('take', $quiz)) {
            throw new AuthorizationException('You are not assigned to this quiz.');
        }
    }

    private function validateQuizAvailability(Quiz $quiz): void
    {
        if (! $quiz->isAvailableToStudents()
            || ! $quiz->questions()->exists()) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is not currently available.',
            ]);
        }
    }

    private function submissionWindowClosed(QuizAttempt $attempt, Quiz $quiz): bool
    {
        return ! $quiz->isAvailableToStudents()
            || ! now()->lt($this->authoritativeDeadline($attempt, $quiz));
    }

    private function lockedResult(Quiz $quiz, User $user): ?QuizResult
    {
        return QuizResult::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
    }

    private function ensureSnapshotsExist(QuizAttempt $attempt, Quiz $quiz): void
    {
        if ($attempt->answers()->exists()) {
            return;
        }

        $questions = $quiz->questions()
            ->with('options')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($questions as $question) {
            $correct = $question->options->firstWhere('is_correct', true);
            $optionsSnapshot = [];

            foreach ($question->options as $option) {
                $optionsSnapshot[] = [
                    'id' => (int) $option->id,
                    'text' => $option->option_text,
                ];
            }

            QuizAttemptAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'correct_option_id' => $correct?->id,
                'question_text_snapshot' => $question->question,
                'question_type_snapshot' => $question->question_type,
                'question_marks_snapshot' => (int) $question->marks,
                'correct_option_text_snapshot' => $correct?->option_text,
                'options_snapshot' => $optionsSnapshot,
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $submittedAnswers
     */
    private function applySubmittedAnswers(QuizAttempt $attempt, array $submittedAnswers): void
    {
        $answers = $attempt->answers()->lockForUpdate()->get()->keyBy('question_id');
        $normalized = $this->normalizeAndValidateAnswers($answers, $submittedAnswers);

        foreach ($answers as $answer) {
            $selectedId = $normalized->get($answer->question_id);
            $selectedOption = collect($answer->options_snapshot)
                ->firstWhere('id', $selectedId);
            $isCorrect = $selectedId !== null
                && $answer->correct_option_id !== null
                && $selectedId === $answer->correct_option_id;

            $answer->update([
                'selected_option_id' => $selectedId,
                'selected_option_text_snapshot' => $selectedOption['text'] ?? null,
                'is_correct' => $isCorrect,
                'awarded_marks' => $isCorrect ? $answer->question_marks_snapshot : 0,
            ]);
        }
    }

    /**
     * @param  Collection<int, QuizAttemptAnswer>  $answers
     * @param  array<int|string, mixed>  $submittedAnswers
     * @return Collection<int, int>
     */
    private function normalizeAndValidateAnswers(
        Collection $answers,
        array $submittedAnswers
    ): Collection {
        $normalized = collect();

        foreach ($submittedAnswers as $questionId => $optionId) {
            if (! ctype_digit((string) $questionId) || ! is_numeric($optionId)) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers must contain valid question and option IDs.',
                ]);
            }

            $questionId = (int) $questionId;
            $optionId = (int) $optionId;
            $answer = $answers->get($questionId);
            $allowedOptionIds = collect($answer?->options_snapshot)->pluck('id')->map(fn ($id) => (int) $id);

            if (! $answer || ! $allowedOptionIds->containsStrict($optionId)) {
                throw ValidationException::withMessages([
                    "answers.{$questionId}" => 'The selected option does not belong to this quiz question.',
                ]);
            }

            $normalized->put($questionId, $optionId);
        }

        return $normalized;
    }

    private function finalize(
        QuizAttempt $attempt,
        Quiz $quiz,
        bool $autoSubmitted
    ): QuizResult {
        $existing = $this->lockedResult($quiz, $attempt->user);

        if ($existing) {
            return $existing;
        }

        $answers = $attempt->answers()->lockForUpdate()->get();
        $score = (int) $answers->sum('awarded_marks');
        $maximumScore = (int) $answers->sum('question_marks_snapshot');
        $participationMarks = $autoSubmitted ? 0 : (int) ($quiz->participation_marks ?? 0);
        $maximumTotalScore = $maximumScore + (int) ($quiz->participation_marks ?? 0);
        $submittedAt = now();

        $attempt->update([
            'submitted_at' => $submittedAt,
            'score' => $score + $participationMarks,
            'status' => $autoSubmitted
                ? QuizAttempt::STATUS_AUTO_SUBMITTED
                : QuizAttempt::STATUS_SUBMITTED,
        ]);

        try {
            return QuizResult::create([
                'quiz_id' => $quiz->id,
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'score' => $score,
                'maximum_score' => $maximumScore,
                'participation_marks' => $participationMarks,
                'total_score' => $score + $participationMarks,
                'maximum_total_score' => $maximumTotalScore,
                'grading_snapshot' => [
                    'quiz_title' => $quiz->title,
                    'quiz_duration_minutes' => (int) $quiz->duration,
                    'quiz_end_time' => $quiz->end_time->toIso8601String(),
                    'authoritative_deadline' => $this->authoritativeDeadline($attempt, $quiz)->toIso8601String(),
                    'auto_submitted' => $autoSubmitted,
                ],
                'graded_at' => $submittedAt,
            ]);
        } catch (QueryException $exception) {
            $result = QuizResult::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $attempt->user_id)
                ->first();

            if ($result) {
                return $result;
            }

            throw $exception;
        }
    }
}
