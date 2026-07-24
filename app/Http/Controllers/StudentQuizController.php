<?php

namespace App\Http\Controllers;

use App\Models\CategoryStudent;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Services\QuizSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function __construct(private readonly QuizSubmissionService $submissions) {}

    public function index()
    {
        $this->authorize('viewAvailable', Quiz::class);

        $user = auth()->user();
        $enrolledCategory = $user->enrolledCategory();
        $availableCategories = QuizCategory::query()
            ->orderBy('category_name')
            ->get();

        $quizzes = Quiz::withCount('questions')
            ->withSum('questions', 'marks')
            ->with(['group', 'questions.options'])
            ->accessibleToStudent($user)
            ->where('end_time', '>=', now())
            ->whereHas('questions')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->isVisibleToStudents())
            ->values();

        $completedQuizIds = QuizResult::where('user_id', auth()->id())
            ->pluck('quiz_id');

        return view('student.quizzes.index', compact(
            'quizzes',
            'completedQuizIds',
            'enrolledCategory',
            'availableCategories',
        ));
    }

    public function enroll(Request $request)
    {
        $this->authorize('viewAvailable', Quiz::class);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:quiz_categories,id'],
        ]);

        $user = auth()->user();
        if ($user->enrolledCategoryId() !== null) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['category_id' => 'You are already enrolled in a quiz title.']);
        }

        CategoryStudent::create([
            'category_id' => $validated['category_id'],
            'user_id' => $user->id,
        ]);

        $category = QuizCategory::find($validated['category_id']);

        return redirect()
            ->route('student.quizzes')
            ->with('success', 'You are now enrolled in '.($category?->category_name ?? 'the selected quiz title').'.');
    }

    public function unenroll(Request $request)
    {
        $this->authorize('viewAvailable', Quiz::class);

        $user = auth()->user();
        $enrollment = CategoryStudent::where('user_id', $user->id)->first();

        if ($enrollment === null) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['category_id' => 'You are not enrolled in any quiz title.']);
        }

        // Block unenroll while an in-progress attempt exists for that category.
        $hasOpenAttempt = Quiz::query()
            ->where('category_id', $enrollment->category_id)
            ->whereHas('attempts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', QuizAttempt::STATUS_IN_PROGRESS);
            })
            ->exists();

        if ($hasOpenAttempt) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['category_id' => 'Finish or wait for your open quiz attempt before unenrolling.']);
        }

        $enrollment->delete();

        return redirect()
            ->route('student.quizzes')
            ->with('success', 'You have been unenrolled from the quiz title.');
    }

    public function progress()
    {
        $this->authorize('viewProgress', Quiz::class);

        $user = auth()->user();
        $results = QuizResult::query()
            ->where('user_id', $user->id)
            ->with(['quiz.group', 'attempt'])
            ->orderByDesc('id')
            ->get();

        $history = $results->map(function (QuizResult $result) use ($user) {
            $percentage = $result->finalPercentage();
            $submittedAt = $this->safeSubmissionDate($result);
            $maximumParticipation = null;

            if ($result->maximum_total_score !== null && $result->maximum_score !== null) {
                $maximumParticipation = max(
                    0,
                    $result->maximum_total_score - $result->maximum_score,
                );
            }

            $reportAvailable = false;
            try {
                $reportAvailable = $result->quiz !== null
                    && now()->gte($result->quiz->end_time)
                    && $user->can('viewPublicReport', $result->quiz);
            } catch (\Throwable) {
                // A malformed legacy schedule must not break the student's history.
            }

            return [
                'result' => $result,
                'quiz' => $result->quiz,
                'submitted_at' => $submittedAt,
                'percentage' => $percentage,
                'maximum_participation' => $maximumParticipation,
                'report_available' => $reportAvailable,
            ];
        });

        $comparable = $history->whereNotNull('percentage')->values();
        $percentages = $comparable->pluck('percentage');
        $latestPercentage = $percentages->first();
        $previousPercentage = $percentages->get(1);
        $trend = $latestPercentage !== null && $previousPercentage !== null
            ? round($latestPercentage - $previousPercentage, 2)
            : null;

        $summary = [
            'total_attempted' => $history->count(),
            'comparable_attempts' => $comparable->count(),
            'average_percentage' => $percentages->isEmpty()
                ? null
                : round($percentages->avg(), 2),
            'highest_percentage' => $percentages->max(),
            'latest_percentage' => $latestPercentage,
            'pass_rate' => $percentages->isEmpty()
                ? null
                : round(
                    ($percentages->filter(fn ($value) => $value >= 50)->count()
                        / $percentages->count()) * 100,
                    2,
                ),
            'trend' => $trend,
        ];

        $chartRows = $comparable->sort(function (array $left, array $right) {
            $leftDate = $left['submitted_at']?->getTimestamp();
            $rightDate = $right['submitted_at']?->getTimestamp();

            if ($leftDate !== null && $rightDate !== null && $leftDate !== $rightDate) {
                return $leftDate <=> $rightDate;
            }
            if ($leftDate === null && $rightDate !== null) {
                return 1;
            }
            if ($leftDate !== null && $rightDate === null) {
                return -1;
            }

            return $left['result']->id <=> $right['result']->id;
        })->values();
        $chartLabels = $chartRows->map(function (array $row) {
            $title = $row['quiz']?->title ?? 'Deleted quiz';
            $date = $row['submitted_at']?->format('M j, Y') ?? 'Date unavailable';

            return "{$title} · {$date} · Result #{$row['result']->id}";
        })->all();
        $chartData = $chartRows->pluck('percentage')->all();

        return view('student.quizzes.progress', compact(
            'history',
            'summary',
            'chartLabels',
            'chartData',
        ));
    }

    public function launchPoll()
    {
        $this->authorize('viewAvailable', Quiz::class);

        $user = auth()->user();
        $now = now();

        $completedQuizIds = QuizResult::where('user_id', $user->id)
            ->pluck('quiz_id')
            ->all();

        $openAttemptQuizIds = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->pluck('quiz_id')
            ->all();

        $quizzes = Quiz::query()
            ->withCount('questions')
            ->accessibleToStudent($user)
            ->where('end_time', '>=', $now)
            ->whereHas('questions')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->isVisibleToStudents()
                && ! in_array($quiz->id, $completedQuizIds, true))
            ->values()
            ->map(function (Quiz $quiz) use ($now, $openAttemptQuizIds) {
                $status = $quiz->lifecycleStatus();
                $secondsUntilStart = max(0, $quiz->start_time->getTimestamp() - $now->getTimestamp());
                $secondsUntilEnd = max(0, $quiz->end_time->getTimestamp() - $now->getTimestamp());
                $hasOpenAttempt = in_array($quiz->id, $openAttemptQuizIds, true);

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'duration_minutes' => (int) $quiz->duration,
                    'questions_count' => (int) $quiz->questions_count,
                    'status' => $status,
                    'start_time' => $quiz->start_time?->toIso8601String(),
                    'end_time' => $quiz->end_time?->toIso8601String(),
                    'seconds_until_start' => (int) $secondsUntilStart,
                    'seconds_until_end' => (int) $secondsUntilEnd,
                    'has_open_attempt' => $hasOpenAttempt,
                    'start_url' => route('student.quiz.show', ['quiz' => $quiz, 'start' => 1]),
                    'preview_url' => route('student.quiz.show', $quiz),
                ];
            });

        return response()->json([
            'server_now' => $now->toIso8601String(),
            'quizzes' => $quizzes,
        ]);
    }

    public function show(Request $request, Quiz $quiz)
    {
        $this->authorize('take', $quiz);

        if (! $quiz->isAvailableToStudents()) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'This quiz is not currently available.']);
        }

        if ($this->hasCompletedQuiz($quiz)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'You have already completed this quiz.']);
        }

        $quiz->load(['questions.options']);

        // If the student hasn't explicitly started the quiz, show a preview
        // page with a Start button. This avoids creating attempts automatically
        // and prevents immediate expiry caused by stale attempts.
        if (! $request->query('start')) {
            return view('student.quizzes.preview', compact('quiz'));
        }

        abort_if($quiz->questions->isEmpty(), 403, 'This quiz has no questions yet.');

        $attempt = $this->submissions->startAttempt(auth()->user(), $quiz);

        if ($attempt instanceof QuizResult) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'This attempt has already been finalized.']);
        }

        $remainingSeconds = $this->submissions->remainingSeconds($attempt, $quiz);

        return view('student.quizzes.show', compact('quiz', 'attempt', 'remainingSeconds'));
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $this->authorize('take', $quiz);

        $validated = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['sometimes', 'array'],
        ]);

        $result = $this->submissions->submit(
            auth()->user(),
            $quiz,
            (int) $validated['attempt_id'],
            $validated['answers'] ?? [],
        );

        $score = $result->score;
        $totalMarks = $result->maximum_score;
        $participationMarks = $result->participation_marks;
        $totalScore = $result->total_score;

        return view('student.quizzes.result', compact(
            'result',
            'quiz',
            'score',
            'totalMarks',
            'participationMarks',
            'totalScore'
        ));
    }

    private function hasCompletedQuiz(Quiz $quiz): bool
    {
        return QuizResult::where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->exists();
    }

    private function safeSubmissionDate(QuizResult $result): ?CarbonImmutable
    {
        $candidates = [
            $result->getRawOriginal('graded_at'),
            $result->attempt?->getRawOriginal('submitted_at'),
            $result->getRawOriginal('created_at'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($candidate);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
