<?php

namespace App\Services;

use App\Models\User;
use App\Models\Topic;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public static function getStatistics()
    {
        return [
            // BASIC STATS
            'totalUsers' => User::count(),
            'totalTopics' => Topic::count(),
            'totalPosts' => Post::count(),
            'totalReplies' => Post::whereNotNull('Parent_Post_ID')->count(),

            // MOST DISCUSSED TOPIC
            'mostDiscussedTopic' => Topic::withCount('posts')
                ->orderBy('posts_count', 'desc')
                ->first(),

            // MOST ACTIVE USER (by total posts + topics)
            'mostActiveUser' => User::select('users.*')
                ->selectRaw('(SELECT COUNT(*) FROM topics WHERE topics.Created_By = users.id) +
                             (SELECT COUNT(*) FROM posts WHERE posts.Created_By = users.id) as activity')
                ->orderByDesc('activity')
                ->first(),
                'recentActivity' => Post::with(['user', 'topic'])
                ->latest()
                ->take(10)
                ->get(),

            // USER ACTIVITY LIST
            'userActivity' => User::select('users.*')
                ->selectRaw('(SELECT COUNT(*) FROM topics WHERE topics.Created_By = users.id) as topics_count')
                ->selectRaw('(SELECT COUNT(*) FROM posts WHERE posts.Created_By = users.id) as posts_count')
                ->selectRaw('(SELECT COUNT(*) FROM posts WHERE posts.Created_By = users.id AND Parent_Post_ID IS NOT NULL) as replies_count')
                ->get()
                ->map(function ($user) {
                    $user->total_activity = $user->topics_count + $user->posts_count + $user->replies_count;
                    return $user;
                })
                ->sortByDesc('total_activity')
                ->values(),
        ];
    }
}