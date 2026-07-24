<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Services\QuizNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class QuizApiController extends Controller
{
    public function __construct(private readonly QuizNotificationService $notifications) {}

    public function index()
    {
        $this->authorize('viewAny', Quiz::class);

        $quizzes = Quiz::manageableBy(Auth::user())
            ->with(['category', 'group'])
            ->withCount('questions')
            ->withSum('questions', 'marks')
            ->latest()
            ->get();

        return response()->json([
            'quizzes' => $quizzes->map(fn (Quiz $quiz) => $this->serializeQuiz($quiz))->values(),
            'count' => $quizzes->count(),
            'categories' => $this->manageableCategories()->map(fn (QuizCategory $category) => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])->values(),
            'groups' => $this->manageableGroups()->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->Group_Name,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'participation_marks' => 'required|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $category = QuizCategory::findOrFail($validated['category_id']);
        $this->authorize('create', [Quiz::class, $group]);
        $this->authorize('assign', $category);

        $quiz = Quiz::create([
            'category_id' => $validated['category_id'],
            'group_id' => $validated['group_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'duration' => $validated['duration'],
            'participation_marks' => $validated['participation_marks'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => Quiz::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        $quiz->load(['category', 'group']);
        $quiz->loadCount('questions');
        $quiz->loadSum('questions', 'marks');

        return response()->json([
            'message' => 'Quiz created successfully. Add questions, then publish it.',
            'quiz' => $this->serializeQuiz($quiz),
        ], 201);
    }

    public function show(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        $quiz->load(['category', 'group', 'questions.options']);
        $quiz->loadCount('questions');
        $quiz->loadSum('questions', 'marks');

        return response()->json([
            'quiz' => $this->serializeQuiz($quiz),
            'questions' => $quiz->questions->values()->map(function ($question, $index) use ($quiz) {
                $options = $question->options->values();
                $correctIndex = $options->search(fn ($option) => (bool) $option->is_correct);
                $letters = ['A', 'B', 'C', 'D'];

                return [
                    'id' => $question->id,
                    'number' => $index + 1,
                    'quiz_id' => $question->quiz_id,
                    'quiz_title' => $quiz->title,
                    'question' => $question->question,
                    'question_type' => $question->question_type,
                    'marks' => (int) $question->marks,
                    'correct_option' => $correctIndex === false ? 0 : (int) $correctIndex,
                    'correct_answer' => $correctIndex === false ? 'A' : ($letters[$correctIndex] ?? 'A'),
                    'options' => $options->map(fn ($option) => [
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'is_correct' => (bool) $option->is_correct,
                    ])->values(),
                    'options_display' => $options->values()->map(function ($option, $optionIndex) use ($letters) {
                        $letter = $letters[$optionIndex] ?? '?';
                        $suffix = $option->is_correct ? ' ✓' : '';

                        return $letter.'. '.$option->option_text.$suffix;
                    })->implode('  |  '),
                ];
            })->values(),
            'can_edit_questions' => $quiz->canEditQuestions(),
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);

        if ($request->filled('group_id')
            && (int) $request->input('group_id') !== (int) $quiz->group_id) {
            $group = Group::findOrFail((int) $request->input('group_id'));
            $this->authorize('create', [Quiz::class, $group]);
        }

        if ($request->filled('category_id')
            && (int) $request->input('category_id') !== (int) $quiz->category_id) {
            $category = QuizCategory::findOrFail((int) $request->input('category_id'));
            $this->authorize('assign', $category);
        }

        $lockedAssignment = ! $quiz->isDraft() || $quiz->hasAssessmentActivity();

        if ($lockedAssignment) {
            if ($request->hasAny([
                'category_id',
                'group_id',
                'participation_marks',
                'status',
            ])) {
                throw ValidationException::withMessages([
                    'quiz' => 'Published quiz assignment, participation marks, and lifecycle status are locked. You can still update the title, description, duration, and schedule.',
                ]);
            }

            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'duration' => ['required', 'integer', 'min:1'],
                'start_time' => ['required', 'date'],
                'end_time' => ['required', 'date', 'after:start_time'],
            ]);

            $quiz->update($validated);
            $quiz->load(['category', 'group']);
            $quiz->loadCount('questions');
            $quiz->loadSum('questions', 'marks');

            return response()->json([
                'message' => 'Quiz schedule and details updated successfully.',
                'quiz' => $this->serializeQuiz($quiz),
            ]);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'participation_marks' => 'required|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $this->authorize('create', [Quiz::class, $group]);

        if ((int) $validated['category_id'] !== (int) $quiz->category_id) {
            $category = QuizCategory::findOrFail($validated['category_id']);
            $this->authorize('assign', $category);
        }

        $quiz->update($validated);
        $quiz->load(['category', 'group']);
        $quiz->loadCount('questions');
        $quiz->loadSum('questions', 'marks');

        return response()->json([
            'message' => 'Quiz updated successfully.',
            'quiz' => $this->serializeQuiz($quiz),
        ]);
    }

    public function publish(Quiz $quiz)
    {
        $this->authorize('publish', $quiz);

        $errors = $quiz->publicationErrors();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($quiz->isPublished()) {
            $this->notifications->notifyPublishedQuiz($quiz);

            return response()->json([
                'message' => 'Quiz is already published.',
                'quiz' => $this->serializeQuiz($quiz->fresh(['category', 'group'])),
            ]);
        }

        $quiz->update([
            'status' => now()->lt($quiz->start_time)
                ? Quiz::STATUS_SCHEDULED
                : Quiz::STATUS_ACTIVE,
        ]);

        $this->notifications->notifyPublishedQuiz($quiz->fresh());

        return response()->json([
            'message' => 'Quiz published successfully and all students have been notified.',
            'quiz' => $this->serializeQuiz($quiz->fresh(['category', 'group'])),
        ]);
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorize('delete', $quiz);

        if (! $quiz->canBeDeleted()) {
            throw ValidationException::withMessages([
                'quiz' => 'Published quizzes or quizzes with attempts/results cannot be deleted.',
            ]);
        }

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully.',
        ]);
    }

    private function serializeQuiz(Quiz $quiz): array
    {
        $user = Auth::user();

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'category_id' => $quiz->category_id,
            'category_name' => $quiz->category->category_name ?? null,
            'group_id' => $quiz->group_id,
            'group_name' => $quiz->group?->Group_Name ?? 'Unassigned',
            'questions_count' => (int) $quiz->questions_count,
            'maximum_marks' => $quiz->authoredMaximumTotal(),
            'duration' => (int) $quiz->duration,
            'participation_marks' => (int) $quiz->participation_marks,
            'start_time' => $quiz->start_time?->format('M j, Y g:i A'),
            'end_time' => $quiz->end_time?->format('M j, Y g:i A'),
            'start_time_iso' => $quiz->start_time?->format('Y-m-d\TH:i'),
            'end_time_iso' => $quiz->end_time?->format('Y-m-d\TH:i'),
            'status' => $quiz->status,
            'lifecycle_status' => $quiz->lifecycleStatus(),
            'is_published' => $quiz->isPublished(),
            'can_publish' => $user->can('publish', $quiz) && ! $quiz->isPublished() && $quiz->questions_count > 0,
            'can_delete' => $user->can('delete', $quiz) && $quiz->canBeDeleted(),
            'can_edit_schedule' => $user->can('update', $quiz),
            'can_edit_assignment' => $user->can('update', $quiz)
                && $quiz->isDraft()
                && ! $quiz->hasAssessmentActivity(),
        ];
    }

    private function manageableGroups()
    {
        $user = Auth::user();

        return $user->isAdmin()
            ? Group::orderBy('Group_Name')->get()
            : $user->teachableGroups()->orderBy('Group_Name')->get();
    }

    private function manageableCategories()
    {
        return QuizCategory::manageableBy(Auth::user())
            ->orderBy('category_name')
            ->get();
    }
}
