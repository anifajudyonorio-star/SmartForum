<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function index()
    {
        $groupIds = auth()->user()->groups()->pluck('groups.id');

        $quizzes = Quiz::withCount('questions')
            ->where('end_time', '>=', now())
            ->whereHas('questions')
            ->when($groupIds->isNotEmpty(), function ($query) use ($groupIds) {
                $query->whereIn('group_id', $groupIds);
            }, function ($query) {
                $query->whereNull('group_id');
            })
            ->orderBy('start_time')
            ->get();

        $completedQuizIds = QuizResult::where('student_id', auth()->id())
            ->pluck('quiz_id');

        return view('student.quizzes.index', compact('quizzes', 'completedQuizIds'));
    }

    public function show(Quiz $quiz)
    {
        if ($this->hasCompletedQuiz($quiz)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'You have already completed this quiz.']);
        }

        if ($quiz->group_id && ! auth()->user()->groups->contains($quiz->group_id)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'You are not assigned to this quiz.']);
        }

        if (! $this->quizIsAvailable($quiz)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'This quiz is not available yet.']);
        }

        $quiz->load(['questions.options']);

        abort_if($quiz->questions->isEmpty(), 403, 'This quiz has no questions yet.');

        // Find existing in-progress attempt for this student.
        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->where('status', 'In Progress')
            ->latest()
            ->first();

        // If there is a stale in-progress attempt (duration expired or quiz ended),
        // mark it submitted/expired so the student can start a fresh attempt.
        if ($attempt) {
            $elapsed = now()->diffInSeconds($attempt->started_at);
            $remainingByDuration = max(0, ($quiz->duration * 60) - $elapsed);
            $remainingByEnd = max(0, $quiz->end_time->diffInSeconds(now()));
            $remainingSeconds = min($remainingByDuration, $remainingByEnd);

            if ($remainingSeconds <= 0) {
                $attempt->update([
                    'submitted_at' => now(),
                    'score' => $attempt->score ?? 0,
                    'status' => 'Auto Submitted',
                ]);

                // clear attempt so a new one will be created below
                $attempt = null;
            }
        }

        // If no valid in-progress attempt exists, create a new one.
        if (! $attempt) {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => auth()->id(),
                'started_at' => now(),
                'status' => 'In Progress',
            ]);
        }

        // Remaining seconds: constrained by per-attempt duration and global quiz end_time
        $elapsed = now()->diffInSeconds($attempt->started_at);
        $remainingByDuration = max(0, ($quiz->duration * 60) - $elapsed);
        $remainingByEnd = max(0, $quiz->end_time->diffInSeconds(now()));
        $remainingSeconds = min($remainingByDuration, $remainingByEnd);

        if ($remainingSeconds <= 0) {
            // No time left for this student to start/continue the quiz
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'The quiz window has closed for you.']);
        }

        return view('student.quizzes.show', compact('quiz', 'attempt', 'remainingSeconds'));
    }

    public function submit(Request $request, Quiz $quiz)
{
    if ($this->hasCompletedQuiz($quiz)) {
        return redirect()
            ->route('student.quizzes')
            ->withErrors(['quiz' => 'You have already completed this quiz.']);
    }

    if ($quiz->group_id && ! auth()->user()->groups->contains($quiz->group_id)) {
        return redirect()
            ->route('student.quizzes')
            ->withErrors(['quiz' => 'You are not assigned to this quiz.']);
    }

    if (! $this->quizIsAvailable($quiz)) {
        return redirect()
            ->route('student.quizzes')
            ->withErrors(['quiz' => 'This quiz is not available yet.']);
    }

    $quiz->load(['questions.options']);

    $score = 0;

    foreach ($quiz->questions as $question) {

        if (!in_array($question->question_type, ['Multiple Choice', 'True/False'])) {
            continue;
        }

        $selected = $request->input('question_'.$question->id);

        $correctOption = $question->options->firstWhere('is_correct', true);

        if ($correctOption && (int)$selected === (int)$correctOption->id) {
            $score += $question->marks;
        }
    }

    $participationMarks = (int) ($quiz->participation_marks ?? 0);
    $totalScore = $score + $participationMarks;

    QuizResult::create([
        'quiz_id' => $quiz->id,
        'student_id' => auth()->id(),
        'score' => $score,
        'participation_marks' => $participationMarks,
        'total_score' => $totalScore,
    ]);

    // Mark attempt as submitted
    $attempt = QuizAttempt::where('quiz_id', $quiz->id)
        ->where('user_id', auth()->id())
        ->where('status', 'In Progress')
        ->latest()
        ->first();

    if ($attempt) {
        $attempt->update([
            'submitted_at' => now(),
            'score' => $totalScore,
            'status' => 'Submitted',
        ]);
    }

    $totalMarks = $quiz->questions->sum('marks');

    return view('student.quizzes.result', compact(
        'quiz',
        'score',
        'totalMarks',
        'participationMarks',
        'totalScore'
    ));
}

    private function quizIsAvailable(Quiz $quiz): bool
{
    return $quiz->start_time <= now() && $quiz->end_time >= now();
}

    private function hasCompletedQuiz(Quiz $quiz): bool
    {
        return QuizResult::where('quiz_id', $quiz->id)
            ->where('student_id', auth()->id())
            ->exists();
    }
}