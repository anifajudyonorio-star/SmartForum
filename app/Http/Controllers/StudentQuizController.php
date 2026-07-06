<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::query()
            ->where('status', 'Active')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $completedQuizIds = QuizResult::query()
            ->where('student_id', auth()->id())
            ->pluck('quiz_id');

        return view('student.quizzes.index', compact('quizzes', 'completedQuizIds'));
    }

    public function show(Quiz $quiz)
    {
        abort_unless($this->quizIsAvailable($quiz), 403, 'This quiz is not currently available.');

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
        abort_unless($this->quizIsAvailable($quiz), 403, 'This quiz is not currently available.');

        if ($this->hasCompletedQuiz($quiz)) {
            return redirect()
                ->route('student.quizzes')
                ->withErrors(['quiz' => 'You have already completed this quiz.']);
        }

        $quiz->load(['questions.options']);

        $score = 0;

        foreach ($quiz->questions as $question) {
            if (! in_array($question->question_type, ['Multiple Choice', 'True/False'], true)) {
                continue;
            }

            $selected = $request->input('question_'.$question->id);
            $correctOption = $question->options->firstWhere('is_correct', true);

            if ($correctOption && (int) $selected === (int) $correctOption->id) {
                $score += $question->marks;
            }
        }

        QuizResult::create([
            'quiz_id' => $quiz->id,
            'student_id' => auth()->id(),
            'score' => $score,
        ]);

        $totalMarks = $quiz->questions->sum('marks');

        return view('student.quizzes.result', compact('quiz', 'score', 'totalMarks'));
    }

    private function quizIsAvailable(Quiz $quiz): bool
    {
        return $quiz->status === 'Active'
            && $quiz->start_time <= now()
            && $quiz->end_time >= now();
    }

    private function hasCompletedQuiz(Quiz $quiz): bool
    {
        return QuizResult::query()
            ->where('quiz_id', $quiz->id)
            ->where('student_id', auth()->id())
            ->exists();
    }
}
