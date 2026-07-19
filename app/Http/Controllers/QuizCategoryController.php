<?php

namespace App\Http\Controllers;

use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', QuizCategory::class);

        $categories = QuizCategory::manageableBy(auth()->user())
            ->withCount('quizzes')
            ->latest()
            ->get();

        return view('quiz_categories.index', compact('categories'));
    }

    public function create()
    {
        $this->authorize('create', QuizCategory::class);

        return view('quiz_categories.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', QuizCategory::class);

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
            ->with('success', 'Quiz title created successfully.');
    }

    public function edit(QuizCategory $quiz_category)
    {
        $this->authorize('update', $quiz_category);

        return view('quiz_categories.edit', ['quizCategory' => $quiz_category]);
    }

    public function update(Request $request, QuizCategory $quiz_category)
    {
        $this->authorize('update', $quiz_category);

        $request->validate([
            'category_name' => 'required|max:255',
            'description' => 'nullable|string',
        ]);

        $quiz_category->update([
            'category_name' => $request->category_name,
            'description' => $request->description,
        ]);

        return redirect()->route('quiz-categories.index')
            ->with('success', 'Quiz title updated successfully.');
    }

    public function destroy(QuizCategory $quiz_category)
    {
        $this->authorize('update', $quiz_category);

        if ($quiz_category->quizzes()->exists()) {
            return back()->withErrors([
                'category' => 'Quiz titles with dependent quizzes cannot be deleted.',
            ]);
        }

        $quiz_category->delete();

        return redirect()->route('quiz-categories.index')
            ->with('success', 'Quiz title deleted successfully.');
    }
}
