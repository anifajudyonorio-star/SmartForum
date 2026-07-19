<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Services\QuizNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    public function __construct(private readonly QuizNotificationService $notifications) {}

    public function index()
    {
        $this->authorize('viewAny', Quiz::class);

        $quizzes = Quiz::manageableBy(auth()->user())
            ->with(['category', 'group'])
            ->withCount('questions')
            ->withSum('questions', 'marks')
            ->latest()
            ->get();

        return view('quizzes.index', compact('quizzes'));
    }

    public function create()
    {
        $this->authorize('create', Quiz::class);

        $categories = $this->manageableCategories();
        $groups = $this->manageableGroups();

        return view('quizzes.create', compact('categories', 'groups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'participation_marks' => 'required|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $group = Group::findOrFail($request->integer('group_id'));
        $category = QuizCategory::findOrFail($request->integer('category_id'));
        $this->authorize('create', [Quiz::class, $group]);
        $this->authorize('assign', $category);

        Quiz::create([
            'category_id' => $request->category_id,
            'group_id' => $request->group_id,
            'title' => $request->title,
            'description' => $request->description,
            'duration' => $request->duration,
            'participation_marks' => $request->participation_marks,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => Quiz::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz created successfully. Add questions, then publish it.');
    }

    public function edit(Quiz $quiz)
    {
        $this->authorize('update', $quiz);

        $categories = $this->manageableCategories($quiz);
        $groups = $this->manageableGroups();

        return view('quizzes.edit', compact('quiz', 'categories', 'groups'));
    }

    public function review(Quiz $quiz)
    {
        $this->authorize('view', $quiz);

        $quiz->load(['questions.options', 'category', 'group']);
        $quiz->loadCount('questions');
        $quiz->loadSum('questions', 'marks');

        return view('quizzes.review', compact('quiz'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorize('update', $quiz);

        if ($request->filled('group_id')
            && $request->integer('group_id') !== (int) $quiz->group_id) {
            $group = Group::findOrFail($request->integer('group_id'));
            $this->authorize('create', [Quiz::class, $group]);
        }

        if ($request->filled('category_id')
            && $request->integer('category_id') !== (int) $quiz->category_id) {
            $category = QuizCategory::findOrFail($request->integer('category_id'));
            $this->authorize('assign', $category);
        }

        if (! $quiz->isDraft() || $quiz->hasAssessmentActivity()) {
            if ($request->hasAny([
                'category_id',
                'group_id',
                'duration',
                'participation_marks',
                'start_time',
                'end_time',
                'status',
            ])) {
                throw ValidationException::withMessages([
                    'quiz' => 'Published quiz assignment, schedule, duration, marks, and lifecycle status are immutable.',
                ]);
            }

            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
            ]);

            $quiz->update($validated);

            return redirect()->route('quizzes.index')
                ->with('success', 'Quiz metadata updated successfully.');
        }

        $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'participation_marks' => 'required|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $group = Group::findOrFail($request->integer('group_id'));
        $this->authorize('create', [Quiz::class, $group]);

        if ($request->integer('category_id') !== (int) $quiz->category_id) {
            $category = QuizCategory::findOrFail($request->integer('category_id'));
            $this->authorize('assign', $category);
        }

        $quiz->update($request->only([
            'category_id',
            'group_id',
            'title',
            'description',
            'duration',
            'participation_marks',
            'start_time',
            'end_time',
        ]));

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz updated successfully.');
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

            return back()->with('success', 'Quiz is already published.');
        }

        $quiz->update([
            'status' => now()->lt($quiz->start_time)
                ? Quiz::STATUS_SCHEDULED
                : Quiz::STATUS_ACTIVE,
        ]);

        $this->notifications->notifyPublishedQuiz($quiz->fresh());

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz published successfully and all students have been notified.');
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorize('delete', $quiz);

        if (! $quiz->canBeDeleted()) {
            return back()->withErrors([
                'quiz' => 'Published quizzes or quizzes with attempts/results cannot be deleted.',
            ]);
        }

        $quiz->delete();

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz deleted successfully.');
    }

    private function manageableGroups()
    {
        $user = auth()->user();

        return $user->isAdmin()
            ? Group::orderBy('Group_Name')->get()
            : $user->teachableGroups()->orderBy('Group_Name')->get();
    }

    private function manageableCategories(?Quiz $quiz = null)
    {
        $user = auth()->user();
        $query = QuizCategory::manageableBy($user);

        if (! $user->isAdmin() && $quiz !== null) {
            $query->orWhere('id', $quiz->category_id);
        }

        return $query->orderBy('category_name')->get();
    }
}
