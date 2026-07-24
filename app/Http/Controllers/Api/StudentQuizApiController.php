<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentQuizController;
use App\Models\CategoryStudent;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Services\QuizSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentQuizApiController extends Controller
{
    public function __construct(private readonly QuizSubmissionService $submissions) {}

    public function index()
    {
        $this->authorize('viewAvailable', Quiz::class);

        $user = Auth::user();
        $enrolledCategory = $user->enrolledCategory();
        $availableCategories = QuizCategory::query()
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

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

        $completedQuizIds = QuizResult::where('user_id', $user->id)
            ->pluck('quiz_id');

        return response()->json([
            'enrolled_category' => $enrolledCategory ? [
                'id' => $enrolledCategory->id,
                'name' => $enrolledCategory->category_name,
            ] : null,
            'available_categories' => $availableCategories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])->values(),
            'quizzes' => $quizzes->map(fn (Quiz $quiz) => $this->serializeListedQuiz($quiz, $completedQuizIds))->values(),
            'completed_quiz_ids' => $completedQuizIds->values(),
        ]);
    }

    public function launchPoll()
    {
        return app(StudentQuizController::class)->launchPoll();
    }

    public function enroll(Request $request)
    {
        $this->authorize('viewAvailable', Quiz::class);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:quiz_categories,id'],
        ]);

        $user = Auth::user();
        if ($user->enrolledCategoryId() !== null) {
            throw ValidationException::withMessages([
                'category_id' => 'You are already enrolled in a quiz title.',
            ]);
        }

        CategoryStudent::create([
            'category_id' => $validated['category_id'],
            'user_id' => $user->id,
        ]);

        $category = QuizCategory::find($validated['category_id']);

        return response()->json([
            'message' => 'You are now enrolled in '.($category?->category_name ?? 'the selected quiz title').'.',
            'enrolled_category' => $category ? [
                'id' => $category->id,
                'name' => $category->category_name,
            ] : null,
        ], 201);
    }

    public function unenroll()
    {
        $this->authorize('viewAvailable', Quiz::class);

        $user = Auth::user();
        $enrollment = CategoryStudent::where('user_id', $user->id)->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'category_id' => 'You are not enrolled in any quiz title.',
            ]);
        }

        $hasOpenAttempt = Quiz::query()
            ->where('category_id', $enrollment->category_id)
            ->whereHas('attempts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', QuizAttempt::STATUS_IN_PROGRESS);
            })
            ->exists();

        if ($hasOpenAttempt) {
            throw ValidationException::withMessages([
                'category_id' => 'Finish or wait for your open quiz attempt before unenrolling.',
            ]);
        }

        $enrollment->delete();

        return response()->json([
            'message' => 'You have been unenrolled from the quiz title.',
            'enrolled_category' => null,
        ]);
    }

    public function show(Request $request, Quiz $quiz)
    {
        $this->authorize('take', $quiz);

        if (! $quiz->isAvailableToStudents()) {
            return response()->json([
                'message' => 'This quiz is not currently available.',
            ], 422);
        }

        $completed = QuizResult::where('quiz_id', $quiz->id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($completed) {
            return response()->json([
                'message' => 'You have already completed this quiz.',
            ], 422);
        }

        $quiz->load(['questions.options']);
        $lifecycle = $quiz->lifecycleStatus();

        if (! $request->boolean('start')) {
            return response()->json([
                'quiz' => $this->serializeQuizDetail($quiz),
                'preview' => true,
                'completed' => false,
                'can_start' => $quiz->isAvailableToStudents(),
                'status_label' => $this->studentStatusLabel($quiz, false),
            ]);
        }

        if (! $quiz->isAvailableToStudents()) {
            return response()->json([
                'message' => 'This quiz is not currently available.',
            ], 422);
        }

        abort_if($quiz->questions->isEmpty(), 403, 'This quiz has no questions yet.');

        $attempt = $this->submissions->startAttempt(Auth::user(), $quiz);

        if ($attempt instanceof QuizResult) {
            return response()->json([
                'message' => 'This attempt has already been finalized.',
            ], 422);
        }

        $remainingSeconds = $this->submissions->remainingSeconds($attempt, $quiz);
        $deadline = $this->submissions->authoritativeDeadline($attempt, $quiz);

        return response()->json([
            'quiz' => $this->serializeQuizDetail($quiz),
            'attempt' => [
                'id' => $attempt->id,
                'remaining_seconds' => $remainingSeconds,
                'deadline_at' => $deadline->toIso8601String(),
            ],
            'questions' => $quiz->questions->map(fn ($question) => [
                'id' => $question->id,
                'question' => $question->question,
                'marks' => $question->marks,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'text' => $option->option_text,
                ])->values(),
            ])->values(),
            'preview' => false,
        ]);
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $this->authorize('take', $quiz);

        $validated = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['sometimes', 'array'],
        ]);

        $result = $this->submissions->submit(
            Auth::user(),
            $quiz,
            (int) $validated['attempt_id'],
            $validated['answers'] ?? [],
        );

        return response()->json([
            'message' => 'Quiz submitted successfully.',
            'result' => [
                'score' => $result->score,
                'total_marks' => $result->maximum_score,
                'participation_marks' => $result->participation_marks,
                'total_score' => $result->total_score,
                'final_possible_marks' => (int) $result->maximum_total_score,
                'percentage' => $result->finalPercentage(),
            ],
        ]);
    }

    private function serializeListedQuiz(Quiz $quiz, $completedQuizIds): array
    {
        $completed = $completedQuizIds->contains($quiz->id);
        $lifecycle = $quiz->lifecycleStatus();

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'questions_count' => (int) $quiz->questions_count,
            'maximum_marks' => $quiz->authoredMaximumTotal(),
            'start_time' => $quiz->start_time?->format('M j, Y g:i A'),
            'end_time' => $quiz->end_time?->format('M j, Y g:i A'),
            'duration' => (int) $quiz->duration,
            'completed' => $completed,
            'can_start' => ! $completed && $quiz->isAvailableToStudents(),
            'status_label' => $this->studentStatusLabel($quiz, $completed),
        ];
    }

    private function serializeQuizDetail(Quiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'duration' => (int) $quiz->duration,
            'participation_marks' => (int) $quiz->participation_marks,
            'question_marks' => $quiz->authoredMarks(),
            'maximum_marks' => $quiz->authoredMaximumTotal(),
            'questions_count' => $quiz->questions->count(),
            'start_time' => $quiz->start_time?->format('M j, Y g:i A'),
            'end_time' => $quiz->end_time?->format('M j, Y g:i A'),
        ];
    }

    private function studentStatusLabel(Quiz $quiz, bool $completed): string
    {
        if ($completed) {
            return 'Completed';
        }

        return $quiz->lifecycleStatus() === Quiz::STATUS_SCHEDULED
            ? 'Upcoming'
            : 'Available';
    }
}
