<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GroupStatisticsService
{
    public static function summaries(?Collection $groupIds = null): Collection
    {
        $query = Group::query()
            ->with('user')
            ->withCount(['topics', 'memberships as members_count'])
            ->orderBy('Group_Name');

        if ($groupIds !== null) {
            $query->whereIn('id', $groupIds);
        }

        return $query->get()
            ->map(function (Group $group) {
                $topicIds = $group->topics()->pluck('id');

                return (object) [
                    'group' => $group,
                    'topics_count' => $group->topics_count,
                    'members_count' => $group->members_count,
                    'posts_count' => $topicIds->isEmpty()
                        ? 0
                        : Post::whereIn('Topic_ID', $topicIds)->count(),
                ];
            });
    }

    public static function forGroup(Group $group): array
    {
        $topicIds = $group->topics()->pluck('id');

        $postsBase = Post::query()->whereIn('Topic_ID', $topicIds);

        $monthLabels = [];
        $monthlyPosts = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthLabels[] = date('M', mktime(0, 0, 0, $month, 1));
            $monthlyPosts[] = (clone $postsBase)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->year)
                ->count();
        }

        $topicStats = Topic::query()
            ->where('Group_ID', $group->id)
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->get();

        $topUsers = User::query()
            ->whereHas('posts', fn ($q) => $q->whereIn('Topic_ID', $topicIds))
            ->withCount(['posts' => fn ($q) => $q->whereIn('Topic_ID', $topicIds)])
            ->orderByDesc('posts_count')
            ->take(5)
            ->get();

        $mostActiveUser = $topUsers->first();
        $mostActiveTopic = $topicStats->first();

        return [
            'group' => $group->load('user'),
            'members_count' => $group->members()->count(),
            'topics_count' => $topicStats->count(),
            'posts_count' => (clone $postsBase)->count(),
            'posts_today' => (clone $postsBase)->whereDate('created_at', Carbon::today())->count(),
            'posts_this_week' => (clone $postsBase)->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count(),
            'posts_this_month' => (clone $postsBase)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'most_active_user' => $mostActiveUser,
            'most_active_topic' => $mostActiveTopic,
            'top_users' => $topUsers,
            'topics' => $topicStats,
            'month_labels' => $monthLabels,
            'monthly_posts' => $monthlyPosts,
            'topic_labels' => $topicStats->pluck('Title')->values()->all(),
            'topic_post_counts' => $topicStats->pluck('posts_count')->values()->all(),
        ];
    }
}
