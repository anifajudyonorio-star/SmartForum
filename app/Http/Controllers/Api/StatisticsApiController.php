<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupStatisticsService;
use App\Services\StatisticsScopeService;
use Illuminate\Support\Facades\Auth;

class StatisticsApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless($user->canViewStatistics(), 403);

        $stats = StatisticsScopeService::buildForUser($user);

        return response()->json([
            'scope' => $stats['scope'],
            'group_summaries' => $stats['group_summaries']->map(fn ($summary) => [
                'group_id' => $summary->group->id,
                'group_name' => $summary->group->Group_Name,
                'members_count' => $summary->members_count,
                'topics_count' => $summary->topics_count,
                'posts_count' => $summary->posts_count,
                'creator_name' => $summary->group->user->name ?? null,
            ])->values(),
            'total_groups' => $stats['total_groups'],
            'total_topics' => $stats['total_topics'],
            'total_posts' => $stats['total_posts'],
            'total_users' => $stats['total_users'],
            'posts_today' => $stats['posts_today'],
            'posts_this_week' => $stats['posts_this_week'],
            'posts_this_month' => $stats['posts_this_month'],
            'most_active_user' => $stats['most_active_user'] ? [
                'name' => $stats['most_active_user']->name,
                'posts_count' => $stats['most_active_user']->posts_count,
            ] : null,
            'most_active_group' => $stats['most_active_group'] ? [
                'name' => $stats['most_active_group']->Group_Name,
                'topics_count' => $stats['most_active_group']->topics_count,
            ] : null,
            'most_active_topic' => $stats['most_active_topic'] ? [
                'title' => $stats['most_active_topic']->Title,
                'posts_count' => $stats['most_active_topic']->posts_count,
            ] : null,
            'top_users' => $stats['top_users']->map(fn ($u) => [
                'name' => $u->name,
                'posts_count' => $u->posts_count,
            ])->values(),
            'group_labels' => $stats['group_labels'],
            'group_posts' => $stats['group_posts'],
            'month_labels' => $stats['month_labels'],
            'monthly_posts' => $stats['monthly_posts'],
            'topic_labels' => $stats['topic_labels']->values()->all(),
            'topic_counts' => $stats['topic_counts']->values()->all(),
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
