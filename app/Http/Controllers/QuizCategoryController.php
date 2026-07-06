<?php

namespace App\Http\Controllers;

use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizCategoryController extends Controller
{
    public function index()
    {
        $categories = QuizCategory::withCount('quizzes')->latest()->get();

        return view('quiz_categories.index', compact('categories'));
    }

    public function create()
    {
        return view('quiz_categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|max:255',
            'description' => 'nullable|string',
        ]);

        QuizCategory::create([
            'category_name' => $request->category_name,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('quiz-categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(QuizCategory $quiz_category)
    {
        return view('quiz_categories.edit', ['quizCategory' => $quiz_category]);
    }

    public function update(Request $request, QuizCategory $quiz_category)
    {
        $request->validate([
            'category_name' => 'required|max:255',
            'description' => 'nullable|string',
        ]);

        $quiz_category->update([
            'category_name' => $request->category_name,
            'description' => $request->description,
        ]);

        return redirect()->route('quiz-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(QuizCategory $quiz_category)
    {
        $quiz_category->delete();

        return redirect()->route('quiz-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
