<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GroupStatisticsService
{
    /**
     * Summary counts shown on the group show page (members, topics, posts, member statuses).
     */
    public static function overviewStats(Collection $members, Collection $topics): array
    {
        $topicIds = $topics->pluck('id');

        $statusCounts = [
            GroupMember::STATUS_ACTIVE => 0,
            GroupMember::STATUS_SUSPENDED => 0,
            GroupMember::STATUS_BLOCKED => 0,
        ];

        foreach ($members as $member) {
            $status = $member->pivot->Member_Status ?? GroupMember::STATUS_ACTIVE;
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        return [
            'members_count' => $members->count(),
            'topics_count' => $topics->count(),
            'posts_count' => $topicIds->isEmpty()
                ? 0
                : Post::whereIn('Topic_ID', $topicIds)->count(),
            'active_members' => $statusCounts[GroupMember::STATUS_ACTIVE],
            'suspended_members' => $statusCounts[GroupMember::STATUS_SUSPENDED],
            'blocked_members' => $statusCounts[GroupMember::STATUS_BLOCKED],
            'most_active_member' => self::topMemberByPosts($members, $topicIds),
            'top_topic_creator' => self::topMemberByTopics($members, $topics),
            'most_active_topic' => self::mostActiveTopic($topics, $topicIds),
            'members_with_warnings' => $members->filter(
                fn ($member) => ((int) ($member->pivot->warnings ?? 0)) > 0
            )->count(),
            'admin_count' => $members->filter(
                fn ($member) => ($member->pivot->Member_Role ?? GroupMember::ROLE_MEMBER) === GroupMember::ROLE_ADMIN
            )->count(),
            'avg_posts_per_topic' => $topics->count() > 0
                ? round((($topicIds->isEmpty()
                    ? 0
                    : Post::whereIn('Topic_ID', $topicIds)->count()) / $topics->count()), 1)
                : 0,
        ];
    }

    private static function topMemberByPosts(Collection $members, Collection $topicIds): ?array
    {
        if ($topicIds->isEmpty()) {
            return null;
        }

        $topUser = User::query()
            ->whereIn('id', $members->pluck('id'))
            ->withCount(['posts' => fn ($query) => $query->whereIn('Topic_ID', $topicIds)])
            ->orderByDesc('posts_count')
            ->first();

        if (! $topUser || $topUser->posts_count === 0) {
            return null;
        }

        return [
            'name' => $topUser->name,
            'count' => $topUser->posts_count,
            'label' => $topUser->posts_count === 1 ? 'post' : 'posts',
        ];
    }

    private static function topMemberByTopics(Collection $members, Collection $topics): ?array
    {
        if ($topics->isEmpty()) {
            return null;
        }

        $creatorCounts = $topics->groupBy('Created_By')->map->count()->sortDesc();
        $topCreatorId = $creatorCounts->keys()->first();
        $topicCount = $creatorCounts->first();

        if (! $topCreatorId || $topicCount === 0) {
            return null;
        }

        $creator = $members->firstWhere('id', $topCreatorId)
            ?? User::find($topCreatorId);

        return [
            'name' => $creator?->name ?? 'Unknown',
            'count' => $topicCount,
            'label' => $topicCount === 1 ? 'topic' : 'topics',
        ];
    }

    private static function mostActiveTopic(Collection $topics, Collection $topicIds): ?array
    {
        if ($topicIds->isEmpty()) {
            return null;
        }

        $mostActiveTopic = Topic::query()
            ->whereIn('id', $topicIds)
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->first();

        if (! $mostActiveTopic || $mostActiveTopic->posts_count === 0) {
            return null;
        }

        return [
            'name' => $mostActiveTopic->Title,
            'count' => $mostActiveTopic->posts_count,
            'label' => $mostActiveTopic->posts_count === 1 ? 'post' : 'posts',
        ];
    }

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
