<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Topic;
use App\Models\User;
use App\Services\GroupStatisticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function latestPosts()
    {
        $groupIds = Auth::user()->viewableGroupIds();

        $latestPosts = Post::with('topic')
            ->whereIn('Topic_ID', Topic::whereIn('Group_ID', $groupIds)->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.partials.latest-posts', compact('latestPosts'));
    }

    public function index()
    {
        $user = Auth::user();
        $groupAdminSummaries = $this->groupAdminSummaries($user);

        if ($user->isAdmin()) {
            return view('dashboard.admin', array_merge($this->adminData(), [
                'groupAdminSummaries' => $groupAdminSummaries,
            ]));
        }

        if ($user->isLecturer()) {
            return view('dashboard.lecturer', array_merge($this->lecturerData(), [
                'groupAdminSummaries' => $groupAdminSummaries,
            ]));
        }

        return view('dashboard.student', array_merge($this->studentData(), [
            'groupAdminSummaries' => $groupAdminSummaries,
        ]));
    }

    private function groupAdminSummaries(User $user)
    {
        if ($user->isAdmin()) {
            return collect();
        }

        return GroupStatisticsService::summaries(
            $user->administeredGroups()->pluck('groups.id')
        );
    }

    private function studentData(): array
    {
        $groupIds = Auth::user()->viewableGroupIds();

        return [
            'myPosts' => Post::where('Created_By', Auth::id())->count(),
            'myTopics' => Topic::where('Created_By', Auth::id())->count(),
            'myReplies' => Post::whereNotNull('Parent_Post_ID')
                ->where('Created_By', Auth::id())
                ->count(),
            'groups' => $groupIds->count(),
            'recentTopics' => Topic::with('group')
                ->whereIn('Group_ID', $groupIds)
                ->latest()
                ->take(5)
                ->get(),
            'latestPosts' => Post::with('topic')
                ->whereIn('Topic_ID', Topic::whereIn('Group_ID', $groupIds)->pluck('id'))
                ->where('created_at', '>=', now()->subDay())
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    private function lecturerData(): array
    {
        $lecturerGroupIds = Auth::user()->viewableGroupIds();

        $participants = User::where(function ($query) {
            $query->whereNull('role')->orWhere('role', 'student');
        })
            ->whereHas('groups', function ($query) use ($lecturerGroupIds) {
                $query->whereIn('groups.id', $lecturerGroupIds);
            })
            ->withCount([
                'topics',
                'posts',
                'posts as replies_count' => function ($query) {
                    $query->whereNotNull('Parent_Post_ID');
                },
            ])->get();

        foreach ($participants as $participant) {
            $participant->score =
                $participant->posts_count +
                $participant->replies_count;
        }

        return [
            'myGroups' => Auth::user()->listedGroupsQuery()->count(),
            'myTopics' => Topic::where('Created_By', Auth::id())->count(),
            'participants' => $participants->sortByDesc('score')->take(10),
            'quizProgress' => $this->lecturerQuizProgress(Auth::user()),
        ];
    }

        private function adminData(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalGroups' => Group::count(),
            'totalTopics' => Topic::count(),
            'totalPosts' => Post::count(),
            'topGroups' => Group::withCount('topics')
                ->orderByDesc('topics_count')
                ->take(5)
                ->get(),
            'topTopics' => Topic::withCount('posts')
                ->orderByDesc('posts_count')
                ->take(5)
                ->get(),
        ];
    }
    /**
     * @return array{
     *     results: Collection<int, QuizResult>,
     *     summary: array<string, int|float|null>,
     *     quizAverages: array{labels: list<string>, values: list<float>},
     *     distribution: array<string, int>,
     *     quizzes: Collection<int, Quiz>
     * }
     */
    private function lecturerQuizProgress(User $lecturer): array
    {
        $results = QuizResult::with(['quiz', 'user', 'attempt'])
            ->whereHas('quiz', fn (Builder $query) => $query->manageableBy($lecturer))
            ->latest('id')
            ->get();

        $percentages = $results
            ->map(fn (QuizResult $result) => $result->finalPercentage())
            ->filter(fn ($value) => $value !== null)
            ->values();

        $studentsAssessed = $results
            ->map(fn (QuizResult $result) => $result->user_id ?? ('name:'.strtolower((string) $result->user?->name)))
            ->filter()
            ->unique()
            ->count();

        $quizAverages = [];
        $quizLabels = [];
        foreach ($results as $result) {
            $percentage = $result->finalPercentage();
            if ($percentage === null || $result->quiz_id === null) {
                continue;
            }

            $quizId = (int) $result->quiz_id;
            $quizAverages[$quizId] ??= ['total' => 0.0, 'count' => 0];
            $quizAverages[$quizId]['total'] += $percentage;
            $quizAverages[$quizId]['count']++;
            $quizLabels[$quizId] = $result->quiz?->title ?? ('Quiz #'.$quizId);
        }

        $averageLabels = [];
        $averageValues = [];
        foreach ($quizAverages as $quizId => $stats) {
            $averageLabels[] = $quizLabels[$quizId];
            $averageValues[] = round($stats['total'] / max(1, $stats['count']), 1);
        }

        $distribution = [
            'Excellent (80%+)' => $percentages->filter(fn ($value) => $value >= 80)->count(),
            'Good (60–79%)' => $percentages->filter(fn ($value) => $value >= 60 && $value < 80)->count(),
            'Pass (50–59%)' => $percentages->filter(fn ($value) => $value >= 50 && $value < 60)->count(),
            'Needs support (<50%)' => $percentages->filter(fn ($value) => $value < 50)->count(),
        ];

        return [
            'results' => $results->take(25),
            'summary' => [
                'submissions' => $results->count(),
                'students_assessed' => $studentsAssessed,
                'average_percentage' => $percentages->isEmpty() ? null : round($percentages->avg(), 1),
                'pass_rate' => $percentages->isEmpty()
                    ? null
                    : round(($percentages->filter(fn ($value) => $value >= 50)->count() / $percentages->count()) * 100, 1),
                'comparable_attempts' => $percentages->count(),
            ],
            'quizAverages' => [
                'labels' => $averageLabels,
                'values' => $averageValues,
            ],
            'distribution' => $distribution,
            'quizzes' => Quiz::manageableBy($lecturer)->orderByDesc('id')->get(['id', 'title']),
        ];
    }

}
