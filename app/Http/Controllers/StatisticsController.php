<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\GroupStatisticsService;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function index()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access this page.');
        }

        $totalGroups = Group::count();
        $totalTopics = Topic::count();
        $totalPosts = Post::count();
        $totalUsers = User::count();

        $mostActiveUser = User::withCount('posts')
            ->orderByDesc('posts_count')
            ->first();

        $mostActiveGroup = Group::withCount('topics')
            ->orderByDesc('topics_count')
            ->first();

        $mostActiveTopic = Topic::withCount('posts')
            ->orderByDesc('posts_count')
            ->first();

        $topUsers = User::withCount('posts')
            ->orderByDesc('posts_count')
            ->take(5)
            ->get();

        $postsToday = Post::whereDate('created_at', Carbon::today())->count();

        $postsThisWeek = Post::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ])->count();

        $postsThisMonth = Post::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $groups = Group::all();

        $groupLabels = [];
        $groupPosts = [];

        foreach ($groups as $group) {
            $groupLabels[] = $group->Group_Name;
            $groupPosts[] = Post::whereIn(
                'Topic_ID',
                $group->topics()->pluck('id')
            )->count();
        }

        $monthLabels = [];
        $monthlyPosts = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthLabels[] = date('M', mktime(0, 0, 0, $month, 1));
            $monthlyPosts[] = Post::whereMonth('created_at', $month)
                ->whereYear('created_at', now()->year)
                ->count();
        }

        $topicLabels = Group::pluck('Group_Name');
        $topicCounts = Group::all()->map(fn ($group) => $group->topics()->count());

        $groupSummaries = GroupStatisticsService::summaries();

        return view('statistics.index', compact(
            'totalGroups',
            'totalTopics',
            'totalPosts',
            'totalUsers',
            'mostActiveUser',
            'mostActiveGroup',
            'mostActiveTopic',
            'topUsers',
            'postsToday',
            'postsThisWeek',
            'postsThisMonth',
            'groupLabels',
            'groupPosts',
            'monthLabels',
            'monthlyPosts',
            'topicLabels',
            'topicCounts',
            'groupSummaries',
        ));
    }

    public function group(Group $group)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access this page.');
        }

        $stats = GroupStatisticsService::forGroup($group);

        return view('statistics.group', $stats);
    }
}
