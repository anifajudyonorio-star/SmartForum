<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    // Display all quizzes
    public function index()
    {
        $quizzes = Quiz::with('category')->get();

        return view('quizzes.index', compact('quizzes'));
    }

    // Show create form
    public function create()
    {
        $categories = QuizCategory::all();

        return view('quizzes.create', compact('categories'));
    }

    // Store new quiz
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
        ->with('success', 'Quiz created successfully!');
}

    // Show edit form
    public function edit($id)
    {
        $quiz = Quiz::findOrFail($id);
        $categories = QuizCategory::all();

        return view('quizzes.edit', compact('quiz', 'categories'));
    }

    // Update quiz
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:quiz_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'duration' => 'required|integer|min:1',
        ]);

        $quiz = Quiz::findOrFail($id);

        $quiz->update([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'duration' => $request->duration,
        ]);

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz updated successfully!');
    }

    // Delete quiz
    public function destroy($id)
    {
        $quiz = Quiz::findOrFail($id);

        $quiz->delete();

        return redirect()->route('quizzes.index')
            ->with('success', 'Quiz deleted successfully!');
    }
}