<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\GroupStatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StatisticsApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless($user->canViewStatistics(), 403);

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
                $scopedTopicIds = Topic::whereIn('Group_ID', $scopedGroupIds)->pluck('id');
                $monthlyPosts[] = $scopedTopicIds->isEmpty()
                    ? 0
                    : Post::whereIn('Topic_ID', $scopedTopicIds)
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', now()->year)
                        ->count();
            } else {
                $monthlyPosts[] = Post::whereMonth('created_at', $month)
                    ->whereYear('created_at', now()->year)
                    ->count();
            }
        }

        $topicLabels = $groups->pluck('Group_Name')->values()->all();
        $topicCounts = $groups->map(fn ($group) => $group->topics()->count())->values()->all();

        return response()->json([
            'group_summaries' => $groupSummaries->map(fn ($summary) => [
                'group_id' => $summary->group->id,
                'group_name' => $summary->group->Group_Name,
                'members_count' => $summary->members_count,
                'topics_count' => $summary->topics_count,
                'posts_count' => $summary->posts_count,
                'lecturer_name' => $summary->group->user->name ?? null,
            ])->values(),
            'total_groups' => $totalGroups,
            'total_topics' => $totalTopics,
            'total_posts' => $totalPosts,
            'total_users' => $totalUsers,
            'posts_today' => $postsToday,
            'posts_this_week' => $postsThisWeek,
            'posts_this_month' => $postsThisMonth,
            'most_active_user' => $mostActiveUser ? [
                'name' => $mostActiveUser->name,
                'posts_count' => $mostActiveUser->posts_count,
            ] : null,
            'most_active_group' => $mostActiveGroup ? [
                'name' => $mostActiveGroup->Group_Name,
                'topics_count' => $mostActiveGroup->topics_count,
            ] : null,
            'most_active_topic' => $mostActiveTopic ? [
                'title' => $mostActiveTopic->Title,
                'posts_count' => $mostActiveTopic->posts_count,
            ] : null,
            'top_users' => $topUsers->map(fn ($u) => [
                'name' => $u->name,
                'posts_count' => $u->posts_count,
            ])->values(),
            'group_labels' => $groupLabels,
            'group_posts' => $groupPosts,
            'month_labels' => $monthLabels,
            'monthly_posts' => $monthlyPosts,
            'topic_labels' => $topicLabels,
            'topic_counts' => $topicCounts,
        ]);
    }

    public function show(Group $group)
    {
        abort_unless(
            Auth::user()->canManageGroup($group),
            403,
            'You do not have permission to view statistics for this group.'
        );

        $stats = GroupStatisticsService::forGroup($group);
        $groupModel = $stats['group'];

        return response()->json([
            'group' => [
                'id' => $groupModel->id,
                'name' => $groupModel->Group_Name,
                'status' => $groupModel->Status,
                'creator_name' => $groupModel->user->name ?? null,
            ],
            'members_count' => $stats['members_count'],
            'topics_count' => $stats['topics_count'],
            'posts_count' => $stats['posts_count'],
            'posts_today' => $stats['posts_today'],
            'posts_this_week' => $stats['posts_this_week'],
            'posts_this_month' => $stats['posts_this_month'],
            'most_active_user' => $stats['most_active_user'] ? [
                'name' => $stats['most_active_user']->name,
                'posts_count' => $stats['most_active_user']->posts_count,
            ] : null,
            'most_active_topic' => $stats['most_active_topic'] ? [
                'title' => $stats['most_active_topic']->Title,
                'posts_count' => $stats['most_active_topic']->posts_count,
            ] : null,
            'top_users' => collect($stats['top_users'])->map(fn ($u) => [
                'name' => $u->name,
                'posts_count' => $u->posts_count,
            ])->values(),
            'topics' => collect($stats['topics'])->map(fn ($topic) => [
                'id' => $topic->id,
                'title' => $topic->Title,
                'posts_count' => $topic->posts_count,
            ])->values(),
            'month_labels' => $stats['month_labels'],
            'monthly_posts' => $stats['monthly_posts'],
            'topic_labels' => $stats['topic_labels'],
            'topic_post_counts' => $stats['topic_post_counts'],
        ]);
    }
}
