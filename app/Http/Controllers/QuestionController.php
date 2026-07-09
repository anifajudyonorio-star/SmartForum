<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::with(['questions.options'])
            ->whereHas('questions')
            ->orderBy('title')
            ->get();

        return view('questions.index', compact('quizzes'));
    }

    public function create()
    {
        $quizzes = Quiz::orderBy('title')->get();

        return view('questions.create', compact('quizzes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string',
            'question_type' => 'required|in:Multiple Choice,True/False,Short Answer',
            'marks' => 'required|integer|min:1',
            'options' => 'required_if:question_type,Multiple Choice,True/False|array|min:2',
            'options.*' => 'nullable|string|max:500',
            'correct_option' => 'required_if:question_type,Multiple Choice,True/False|integer|min:0',
        ]);

        $question = Question::create([
            'quiz_id' => $request->quiz_id,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'marks' => $request->marks,
        ]);

        if (in_array($request->question_type, ['Multiple Choice', 'True/False'], true)) {
            foreach ($request->options as $index => $optionText) {
                if (blank($optionText)) {
                    continue;
                }

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => (int) $request->correct_option === (int) $index,
                ]);
            }
        }

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question added successfully.');
    }

    public function edit(Question $question)
    {
        $quizzes = Quiz::orderBy('title')->get();
        $question->load('options');

        return view('questions.edit', compact('question', 'quizzes'));
    }

    public function update(Request $request, Question $question)
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string',
            'question_type' => 'required|in:Multiple Choice,True/False,Short Answer',
            'marks' => 'required|integer|min:1',
            'options' => 'required_if:question_type,Multiple Choice,True/False|array|min:2',
            'options.*' => 'nullable|string|max:500',
            'correct_option' => 'required_if:question_type,Multiple Choice,True/False|integer|min:0',
        ]);

        $question->update([
            'quiz_id' => $request->quiz_id,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'marks' => $request->marks,
        ]);

        $question->options()->delete();

        if (in_array($request->question_type, ['Multiple Choice', 'True/False'], true)) {
            foreach ($request->options as $index => $optionText) {
                if (blank($optionText)) {
                    continue;
                }

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => (int) $request->correct_option === (int) $index,
                ]);
            }
        }

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return redirect()
            ->route('questions.index')
            ->with('success', 'Question deleted successfully.');
    }
}
