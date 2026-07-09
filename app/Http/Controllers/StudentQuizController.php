<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::withCount('questions')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereHas('questions')
            ->orderBy('title')
            ->get();

        $completedQuizIds = QuizResult::where('student_id', auth()->id())
            ->pluck('quiz_id');

        return view('student.quizzes.index', compact('quizzes', 'completedQuizIds'));
    }

    public function show(Quiz $quiz)
    {
        

        if ($this->hasCompletedQuiz($quiz)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'You have already completed this quiz.']);
        }

        $quiz->load(['questions.options']);

        abort_if($quiz->questions->isEmpty(), 403, 'This quiz has no questions yet.');

        return view('student.quizzes.show', compact('quiz'));
    }

    public function submit(Request $request, Quiz $quiz)
{
    if ($this->hasCompletedQuiz($quiz)) {
        return redirect()
            ->route('student.quizzes')
            ->withErrors(['quiz' => 'You have already completed this quiz.']);
    }

    $quiz->load(['questions.options']);

    $score = 0;

    foreach ($quiz->questions as $question) {

        if (!in_array($question->question_type, ['Multiple Choice', 'True/False'])) {
            continue;
        }

        $selected = $request->input('question_'.$question->id);

        $correctOption = $question->options->firstWhere('is_correct', true);

        if ($correctOption && (int)$selected === (int)$correctOption->id) {
            $score += $question->marks;
        }
    }

    $participationMarks = (int) ($quiz->participation_marks ?? 0);
    $totalScore = $score + $participationMarks;

    QuizResult::create([
        'quiz_id' => $quiz->id,
        'student_id' => auth()->id(),
        'score' => $score,
        'participation_marks' => $participationMarks,
        'total_score' => $totalScore,
    ]);

    $totalMarks = $quiz->questions->sum('marks');

    return view('student.quizzes.result', compact(
        'quiz',
        'score',
        'totalMarks',
        'participationMarks',
        'totalScore'
    ));
}

    private function quizIsAvailable(Quiz $quiz): bool
{
    return true;
}

    private function hasCompletedQuiz(Quiz $quiz): bool
    {
        return QuizResult::where('quiz_id', $quiz->id)
            ->where('student_id', auth()->id())
            ->exists();
    }
}