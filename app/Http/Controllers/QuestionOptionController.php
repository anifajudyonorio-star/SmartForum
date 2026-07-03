<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;

class QuestionOptionController extends Controller
{
    /**
     * Display all options.
     */
    public function index()
    {
        $options = QuestionOption::with('question')->latest()->get();

        return view('question_options.index', compact('options'));
    }

    /**
     * Show the form to create a new option.
     */
    public function create()
    {
        $questions = Question::all();

        return view('question_options.create', compact('questions'));
    }

    /**
     * Store a new option.
     */
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'option_text' => 'required|string',
            'is_correct' => 'required',
        ]);

        QuestionOption::create([
            'question_id' => $request->question_id,
            'option_text' => $request->option_text,
            'is_correct' => $request->is_correct,
        ]);

        return redirect()
            ->route('question-options.index')
            ->with('success', 'Option added successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit($id)
    {
        $option = QuestionOption::findOrFail($id);
        $questions = Question::all();

        return view('question_options.edit', compact('option', 'questions'));
    }

    /**
     * Update an option.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'option_text' => 'required|string',
            'is_correct' => 'required',
        ]);

        $option = QuestionOption::findOrFail($id);

        $option->update([
            'question_id' => $request->question_id,
            'option_text' => $request->option_text,
            'is_correct' => $request->is_correct,
        ]);

        return redirect()
            ->route('question-options.index')
            ->with('success', 'Option updated successfully.');
    }

    /**
     * Delete an option.
     */
    public function destroy($id)
    {
        $option = QuestionOption::findOrFail($id);

        $option->delete();

        return redirect()
            ->route('question-options.index')
            ->with('success', 'Option deleted successfully.');
    }
}