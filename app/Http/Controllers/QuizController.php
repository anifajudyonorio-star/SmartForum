<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Quiz;
use App\Models\Notification;
use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index()
{
    $quizzes = Quiz::with(['category', 'group'])
        ->withCount('questions')
        ->latest()
        ->get();

    foreach ($quizzes as $quiz) {

        if (now()->lt($quiz->start_time)) {

            $quiz->status = 'Scheduled';

        } elseif (now()->between($quiz->start_time, $quiz->end_time)) {

            $quiz->status = 'Active';

        } else {

            $quiz->status = 'Closed';

        }

    }

    return view('quizzes.index', compact('quizzes'));
}

    public function create()
    {
        $categories = QuizCategory::orderBy('category_name')->get();
        $groups = Group::orderBy('Group_Name')->get();

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

        Quiz::create([
    'category_id' => $request->category_id,
    'group_id' => $request->group_id,
    'title' => $request->title,
    'description' => $request->description,
    'duration' => $request->duration,
    'participation_marks' => $request->participation_marks,
    'start_time' => $request->start_time,
    'end_time' => $request->end_time,
    'status' => 'Scheduled',
    'created_by' => auth()->id(),
]);

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz created successfully. Add questions, then publish it.');
    }

    public function edit(Quiz $quiz)
    {
        $categories = QuizCategory::orderBy('category_name')->get();
        $groups = Group::orderBy('Group_Name')->get();

        return view('quizzes.edit', compact('quiz', 'categories', 'groups'));
    }

    public function review(Quiz $quiz)
    {
        $quiz->load(['questions.options', 'category', 'group']);
        $quiz->loadCount('questions');

        return view('quizzes.review', compact('quiz'));
    }

    public function update(Request $request, Quiz $quiz)
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
    'status' => 'required|in:Draft,Scheduled,Active,Closed',
]);

        $quiz->update($request->only([
    'category_id',
    'group_id',
    'title',
    'description',
    'duration',
    'participation_marks',
    'start_time',
    'end_time',
    'status',
]));

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz updated successfully.');
    }

    public function publish(Quiz $quiz)
{
    if ($quiz->questions()->count() == 0) {
        return back()->withErrors([
            'quiz' => 'Please add at least one question before publishing the quiz.'
        ]);
    }

    if ($quiz->status === 'Active') {
        return back()->with('success', 'Quiz is already published.');
    }

    $quiz->update([
        'status' => 'Active'
    ]);

    $students = \App\Models\User::where('role', 'student')
        ->whereHas('groups', function ($query) use ($quiz) {
            $query->where('groups.id', $quiz->group_id);
        })
        ->get();

    foreach ($students as $student) {

        \App\Models\Notification::create([

            'user_ID' => $student->id,

            'Notification_Type' => 'Quiz',

            'Notification_Title' => 'New Quiz Available',

            'Message' => 'A new quiz "' . $quiz->title . '" is scheduled for '
                . $quiz->start_time->format('d M Y H:i')
                . ' and closes on '
                . $quiz->end_time->format('d M Y H:i') . '.',

            'Is_Read' => false,

            'Post_ID' => null,

            'quiz_id' => $quiz->id,

            'expires_at' => $quiz->end_time,

        ]);

    }

    return redirect()->route('quizzes.index')
        ->with('success', 'Quiz published successfully and all students have been notified.');
}

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz deleted successfully.');
    }
}
