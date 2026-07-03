<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\QuizResult;

class StudentQuizController extends Controller
{
    /**
     * Display all available quizzes.
     */
    public function index()
    {
        $quizzes = Quiz::all();

        return view('student.quizzes.index', compact('quizzes'));
    }
    public function show($id)
{
    $quiz = Quiz::with('questions.options')->findOrFail($id);

    return view('student.quizzes.show', compact('quiz'));
}
public function submit(Request $request, $id)
{
    $quiz = Quiz::with('questions.options')->findOrFail($id);

    $score = 0;

    foreach ($quiz->questions as $question) {

        $selected = $request->input('question_' . $question->id);

        $correctOption = $question->options
            ->where('is_correct', true)
            ->first();

        if ($correctOption && $selected == $correctOption->id) {
            $score += $question->marks;
        }
    }

    // Save the student's result
    QuizResult::create([
        'quiz_id' => $quiz->id,
        'student_id' => auth()->id(), // Replace with auth()->id() after login is implemented
        'score' => $score,
    ]);

    return view('student.quizzes.result', compact('quiz', 'score'));
}
}