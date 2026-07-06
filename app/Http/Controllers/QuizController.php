<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::with('category')->withCount('questions')->latest()->get();

        return view('quizzes.index', compact('quizzes'));
    }

    public function create()
    {
        $categories = QuizCategory::orderBy('category_name')->get();

        return view('quizzes.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        Quiz::create([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'duration' => $request->duration,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'Draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz created successfully. Add questions, then publish it.');
    }

    public function edit(Quiz $quiz)
    {
        $categories = QuizCategory::orderBy('category_name')->get();

        return view('quizzes.edit', compact('quiz', 'categories'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'status' => 'required|in:Draft,Scheduled,Active,Closed',
        ]);

        $quiz->update($request->only([
            'category_id',
            'title',
            'description',
            'duration',
            'start_time',
            'end_time',
            'status',
        ]));

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz updated successfully.');
    }

    public function publish(Quiz $quiz)
    {
        if ($quiz->questions()->count() === 0) {
            return back()->withErrors(['quiz' => 'Add at least one question before publishing.']);
        }

        $quiz->update(['status' => 'Active']);

        return back()->with('success', 'Quiz published and available to students.');
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz deleted successfully.');
    }
}
