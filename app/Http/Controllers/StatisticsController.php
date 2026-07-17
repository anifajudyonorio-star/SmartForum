<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\StatisticsScopeService;
use Illuminate\Support\Facades\Auth;

class StatisticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless($user->canViewStatistics(), 403, 'You do not have permission to access this page.');

        $stats = StatisticsScopeService::buildForUser($user);

        return view('statistics.index', array_merge($stats, [
            'groupSummaries' => $stats['group_summaries'],
            'totalGroups' => $stats['total_groups'],
            'totalTopics' => $stats['total_topics'],
            'totalPosts' => $stats['total_posts'],
            'totalUsers' => $stats['total_users'],
            'postsToday' => $stats['posts_today'],
            'postsThisWeek' => $stats['posts_this_week'],
            'postsThisMonth' => $stats['posts_this_month'],
            'mostActiveUser' => $stats['most_active_user'],
            'mostActiveGroup' => $stats['most_active_group'],
            'mostActiveTopic' => $stats['most_active_topic'],
            'topUsers' => $stats['top_users'],
            'groupLabels' => $stats['group_labels'],
            'groupPosts' => $stats['group_posts'],
            'monthLabels' => $stats['month_labels'],
            'monthlyPosts' => $stats['monthly_posts'],
            'topicLabels' => $stats['topic_labels'],
            'topicCounts' => $stats['topic_counts'],
            'isSystemScope' => $stats['scope'] === 'system',
        ]));
    }

    public function group(Group $group)
    {
        abort_unless(
            Auth::user()->canManageGroup($group),
            403,
            'You do not have permission to view statistics for this group.'
        );

        $stats = \App\Services\GroupStatisticsService::forGroup($group);

        return view('statistics.group', $stats);
    }
}
