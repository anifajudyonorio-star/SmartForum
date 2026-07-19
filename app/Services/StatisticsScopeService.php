<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatisticsScopeService
{
    /**
     * Build forum statistics payload scoped to the user's role.
     * System admins see all groups; group admins see only groups they administer.
     *
     * @return array<string, mixed>
     */
    public static function buildForUser(User $user): array
    {
        $isSystemScope = $user->isAdmin();
        $scopedGroupIds = $isSystemScope
            ? null
            : $user->administeredGroups()->pluck('groups.id');

        $groupSummaries = $isSystemScope
            ? GroupStatisticsService::summaries()
            : GroupStatisticsService::summaries($scopedGroupIds);

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
            $topicIds = null;
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

        return [
            'scope' => $isSystemScope ? 'system' : 'group_admin',
            'group_summaries' => $groupSummaries,
            'total_groups' => $totalGroups,
            'total_topics' => $totalTopics,
            'total_posts' => $totalPosts,
            'total_users' => $totalUsers,
            'posts_today' => $postsToday,
            'posts_this_week' => $postsThisWeek,
            'posts_this_month' => $postsThisMonth,
            'most_active_user' => $mostActiveUser,
            'most_active_group' => $mostActiveGroup,
            'most_active_topic' => $mostActiveTopic,
            'top_users' => $topUsers,
            'group_labels' => $groupLabels,
            'group_posts' => $groupPosts,
            'month_labels' => $monthLabels,
            'monthly_posts' => $monthlyPosts,
            'topic_labels' => $groups->pluck('Group_Name'),
            'topic_counts' => $groups->map(fn ($group) => $group->topics()->count()),
        ];
    }

    /**
     * Groups a user may view participation for.
     */
    public static function participationGroupsFor(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Group::orderBy('Group_Name')->get();
        }

        if ($user->administeredGroups()->exists()) {
            return $user->administeredGroups()->orderBy('Group_Name')->get();
        }

        if ($user->isLecturer()) {
            return $user->groups()->orderBy('Group_Name')->get();
        }

        return collect();
    }
}
