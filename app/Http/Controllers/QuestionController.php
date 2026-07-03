<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Display all questions.
     */
    public function index()
    {
        $questions = Question::with('quiz')->latest()->get();

        return view('questions.index', compact('questions'));
    }

    /**
     * Show the Add Question form.
     */
    public function create()
    {
        $quizzes = Quiz::all();

        return view('questions.create', compact('quizzes'));
    }

    /**
     * Save a new question.
     */
    public function store(Request $request)
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string',
            'question_type' => 'required|string',
            'marks' => 'required|integer|min:1',
        ]);

        Question::create([
            'quiz_id' => $request->quiz_id,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'marks' => $request->marks,
        ]);

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question added successfully.');
    }

    /**
     * Show the Edit Question form.
     */
    public function edit($id)
    {
        $question = Question::findOrFail($id);
        $quizzes = Quiz::all();

        return view('questions.edit', compact('question', 'quizzes'));
    }

    /**
     * Update a question.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string',
            'question_type' => 'required|string',
            'marks' => 'required|integer|min:1',
        ]);

        $question = Question::findOrFail($id);

        $question->update([
            'quiz_id' => $request->quiz_id,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'marks' => $request->marks,
        ]);

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question updated successfully.');
    }

    /**
     * Delete a question.
     */
    public function destroy($id)
    {
        $question = Question::findOrFail($id);

        $question->delete();

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question deleted successfully.');
    }
}