<?php

namespace App\Services;

use App\Models\User;
use App\Models\Topic;
use App\Models\Post;

class ParticipationService
{
    public static function getParticipation()
    {
        $users = User::all();

        foreach ($users as $user) {

            $user->topics = Topic::where('Created_By', $user->id)->count();

            $user->posts = Post::where('Created_By', $user->id)
                ->whereNull('Parent_Post_ID')
                ->count();

            $user->replies = Post::where('Created_By', $user->id)
                ->whereNotNull('Parent_Post_ID')
                ->count();

            $user->total = $user->topics + $user->posts + $user->replies;
        }

        return $users;
    }
}