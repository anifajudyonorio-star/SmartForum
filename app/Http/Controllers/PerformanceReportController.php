<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PerformanceReportController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Quiz::class);

        $results = QuizResult::with(['quiz', 'user', 'attempt'])
            ->whereHas('quiz', fn (Builder $query) => $query->manageableBy(auth()->user()))
            ->latest()
            ->get();

        $percentages = $results->map->finalPercentage()->filter(fn ($value) => $value !== null);
        $averagePercentage = $percentages->isEmpty() ? null : round($percentages->avg(), 2);
        $highestPercentage = $percentages->max();
        $passRate = $percentages->isEmpty()
            ? null
            : round(($percentages->filter(fn ($value) => $value >= 50)->count() / $percentages->count()) * 100, 2);
        $totalAttempts = $results->count();
        $comparableAttempts = $percentages->count();

        return view('reports.index', compact(
            'results',
            'averagePercentage',
            'highestPercentage',
            'passRate',
            'totalAttempts',
            'comparableAttempts',
        ));
    }

    public function quiz(Quiz $quiz)
    {
        $this->authorize('viewReports', $quiz);

        return $this->renderLecturerQuizReport($quiz);
    }

    public function publicQuiz(Quiz $quiz)
    {
        $this->authorize('viewPublicReport', $quiz);

        if (auth()->user()->isStudent()) {
            return $this->renderStudentQuizReport($quiz);
        }

        return $this->renderLecturerQuizReport($quiz);
    }

    private function renderLecturerQuizReport(Quiz $quiz)
    {
        if (now()->lt($quiz->end_time)) {
            return redirect()->back()->withErrors(['report' => 'Report is available after the quiz ends.']);
        }

        if ($quiz->group_id) {
            $members = $quiz->group->members()
                ->where(function (Builder $query) {
                    $query->whereNull('role')->orWhere('role', 'student');
                })
                ->wherePivot('Member_Status', GroupMember::STATUS_ACTIVE)
                ->get();

            $submittedUsers = User::where(function (Builder $query) {
                $query->whereNull('role')->orWhere('role', 'student');
            })
                ->whereHas('quizResults', function (Builder $query) use ($quiz) {
                    $query->where('quiz_id', $quiz->id);
                })
                ->get();
            $members = $members->merge($submittedUsers)->unique('id')->values();
        } else {
            $members = User::whereHas('quizResults', function (Builder $query) use ($quiz) {
                $query->where('quiz_id', $quiz->id);
            })->get();
        }

        $results = QuizResult::with('attempt')->where('quiz_id', $quiz->id)->get()->keyBy('user_id');
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)->get()->keyBy('user_id');

        $rows = $members->map(function ($member) use ($results, $attempts) {
            $result = $results->get($member->id);
            $attempt = $attempts->get($member->id);
            $status = $result?->submissionStatus()
                ?? ($attempt ? 'Timed Out / Not Finalized' : 'Not Attempted');

            return [
                'user' => $member,
                'result' => $result,
                'score' => $result?->total_score,
                'maximum_score' => $result?->maximum_total_score,
                'percentage' => $result?->finalPercentage(),
                'status' => $status,
            ];
        });

        $percentages = $rows->pluck('percentage')->filter(fn ($value) => $value !== null);
        $metrics = [
            'audience_count' => $rows->count(),
            'submitted_count' => $rows->whereNotNull('result')->count(),
            'not_submitted_count' => $rows->whereNull('result')->count(),
            'timed_out_count' => $rows->filter(fn ($row) => str_contains($row['status'], 'Timed Out'))->count(),
            'average_percentage' => $percentages->isEmpty() ? null : round($percentages->avg(), 2),
            'pass_rate' => $percentages->isEmpty()
                ? null
                : round(($percentages->filter(fn ($value) => $value >= 50)->count() / $percentages->count()) * 100, 2),
            'distribution' => $this->distribution($percentages),
        ];

        return view('reports.quiz', compact('quiz', 'rows', 'metrics'));
    }

    private function renderStudentQuizReport(Quiz $quiz)
    {
        if (now()->lt($quiz->end_time)) {
            return redirect()->back()->withErrors(['report' => 'Shared performance is available after the quiz ends.']);
        }

        $personalResult = QuizResult::with('attempt')
            ->where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->first();
        $percentages = QuizResult::with('attempt')
            ->where('quiz_id', $quiz->id)
            ->get()
            ->map->finalPercentage()
            ->filter(fn ($value) => $value !== null);
        $privacyThreshold = 5;
        $summary = null;

        if ($percentages->count() >= $privacyThreshold) {
            $summary = [
                'submission_count' => $percentages->count(),
                'average_percentage' => round($percentages->avg(), 2),
                'pass_rate' => round(
                    ($percentages->filter(fn ($value) => $value >= 50)->count() / $percentages->count()) * 100,
                    2,
                ),
                'distribution' => $this->distribution($percentages),
            ];
        }

        return view('reports.student-quiz', compact(
            'quiz',
            'personalResult',
            'summary',
            'privacyThreshold',
        ));
    }

    /**
     * @return array<string, int>
     */
    private function distribution(Collection $percentages): array
    {
        return [
            '0–49%' => $percentages->filter(fn ($value) => $value < 50)->count(),
            '50–69%' => $percentages->filter(fn ($value) => $value >= 50 && $value < 70)->count(),
            '70–84%' => $percentages->filter(fn ($value) => $value >= 70 && $value < 85)->count(),
            '85–100%' => $percentages->filter(fn ($value) => $value >= 85)->count(),
        ];
    }
}
