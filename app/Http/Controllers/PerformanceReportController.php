<?php

namespace App\Http\Controllers;

use App\Models\QuizResult;
use App\Models\Quiz;
use App\Models\User;

class PerformanceReportController extends Controller
{
    public function index()
    {
        $results = QuizResult::with(['quiz', 'student'])
            ->latest()
            ->get();

        $averageScore = round($results->avg('total_score'), 2);

        $highestScore = $results->max('total_score');

        $lowestScore = $results->min('total_score');

        $totalAttempts = $results->count();

        return view('reports.index', compact(
            'results',
            'averageScore',
            'highestScore',
            'lowestScore',
            'totalAttempts'
        ));
    }

    public function quiz(Quiz $quiz)
    {
        // Only allow after quiz end time
        if (now()->lt($quiz->end_time)) {
            return redirect()->back()->withErrors(['report' => 'Report is available after the quiz ends.']);
        }

        // Get students who should be part of this quiz audience
        if ($quiz->group_id) {
            $members = $quiz->group->members()->get();
        } else {
            $members = User::where('role', 'student')->get();
        }

        $results = QuizResult::where('quiz_id', $quiz->id)->get()->keyBy('user_id');

        $rows = $members->map(function ($member) use ($results) {
            $result = $results->get($member->id);

            return [
                'student' => $member,
                'score' => $result?->total_score,
                'status' => $result ? 'Submitted' : 'Not Attempted',
            ];
        });

        return view('reports.quiz', compact('quiz', 'rows'));
    }

    public function publicQuiz(Quiz $quiz)
    {
        if (now()->lt($quiz->end_time)) {
            return redirect()->back()->withErrors(['report' => 'Report is available after the quiz ends.']);
        }

        $user = auth()->user();

        if ($user->isAdmin() || $user->isLecturer()) {
            return $this->quiz($quiz);
        }

        if ($quiz->group_id && ! $user->groups->contains($quiz->group_id)) {
            abort(403, 'You are not allowed to view this report.');
        }

        return $this->quiz($quiz);
    }
}