<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\GroupStatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StatisticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless($user->canViewStatistics(), 403, 'You do not have permission to access this page.');

        // Group admins only see groups they administer; system admins see everything.
        if ($user->isAdmin()) {
            $groupSummaries = GroupStatisticsService::summaries();
            $scopedGroupIds = null;
        } else {
            $administered = $user->administeredGroups()->with('user')->get();
            $scopedGroupIds = $administered->pluck('id');
            $groupSummaries = GroupStatisticsService::summaries($scopedGroupIds);
        }

        if ($scopedGroupIds !== null) {
            $totalGroups = $scopedGroupIds->count();
            $totalTopics = Topic::whereIn('Group_ID', $scopedGroupIds)->count();
            $topicIds = Topic::whereIn('Group_ID', $scopedGroupIds)->pluck('id');
            $totalPosts = $topicIds->isEmpty() ? 0 : Post::whereIn('Topic_ID', $topicIds)->count();
            $totalUsers = Group::whereIn('id', $scopedGroupIds)
                ->withCount('memberships')
                ->get()
                ->sum('memberships_count');

            $groups = Group::whereIn('id', $scopedGroupIds)->get();
            $postsToday = $topicIds->isEmpty() ? 0 : Post::whereIn('Topic_ID', $topicIds)->whereDate('created_at', Carbon::today())->count();
            $postsThisWeek = $topicIds->isEmpty() ? 0 : Post::whereIn('Topic_ID', $topicIds)->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count();
            $postsThisMonth = $topicIds->isEmpty() ? 0 : Post::whereIn('Topic_ID', $topicIds)
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            $mostActiveUser = $topicIds->isEmpty()
                ? null
                : User::withCount(['posts' => fn ($q) => $q->whereIn('Topic_ID', $topicIds)])
                    ->orderByDesc('posts_count')
                    ->first();

            $mostActiveGroup = Group::whereIn('id', $scopedGroupIds)
                ->withCount('topics')
                ->orderByDesc('topics_count')
                ->first();

            $mostActiveTopic = Topic::whereIn('Group_ID', $scopedGroupIds)
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->first();

            $topUsers = $topicIds->isEmpty()
                ? collect()
                : User::withCount(['posts' => fn ($q) => $q->whereIn('Topic_ID', $topicIds)])
                    ->orderByDesc('posts_count')
                    ->take(5)
                    ->get();
        } else {
            $totalGroups = Group::count();
            $totalTopics = Topic::count();
            $totalPosts = Post::count();
            $totalUsers = User::count();
            $groups = Group::all();
            $postsToday = Post::whereDate('created_at', Carbon::today())->count();
            $postsThisWeek = Post::whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count();
            $postsThisMonth = Post::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();
            $mostActiveUser = User::withCount('posts')->orderByDesc('posts_count')->first();
            $mostActiveGroup = Group::withCount('topics')->orderByDesc('topics_count')->first();
            $mostActiveTopic = Topic::withCount('posts')->orderByDesc('posts_count')->first();
            $topUsers = User::withCount('posts')->orderByDesc('posts_count')->take(5)->get();
        }

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

            if ($scopedGroupIds !== null) {
                $topicIds = Topic::whereIn('Group_ID', $scopedGroupIds)->pluck('id');
                $monthlyPosts[] = $topicIds->isEmpty()
                    ? 0
                    : Post::whereIn('Topic_ID', $topicIds)
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', now()->year)
                        ->count();
            } else {
                $monthlyPosts[] = Post::whereMonth('created_at', $month)
                    ->whereYear('created_at', now()->year)
                    ->count();
            }
        }

        $topicLabels = $groups->pluck('Group_Name');
        $topicCounts = $groups->map(fn ($group) => $group->topics()->count());

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
        abort_unless(
            Auth::user()->canManageGroup($group),
            403,
            'You do not have permission to view statistics for this group.'
        );

        $stats = GroupStatisticsService::forGroup($group);

        return view('statistics.group', $stats);
    }
}
