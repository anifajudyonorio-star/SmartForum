<?php

namespace App\Http\Controllers;

use App\Models\User;

class ParticipationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403, 'You do not have permission to access this page.');
        }

        $participants = User::where(function ($query) {
            $query->whereNull('role')->orWhere('role', 'student');
        });

        if ($user->isLecturer() && ! $user->isAdmin()) {
            $lecturerGroupIds = $user->groups()->pluck('groups.id');
            $participants->whereHas('groups', function ($query) use ($lecturerGroupIds) {
                $query->whereIn('groups.id', $lecturerGroupIds);
            });
        }

        $participants = $participants->withCount([
            'topics',
            'posts',
            'posts as replies_count' => function ($query) {
                $query->whereNotNull('Parent_Post_ID');
            }
        ])->get();

        foreach ($participants as $participant) {

            $participant->score =
                $participant->topics_count +
                $participant->posts_count +
                $participant->replies_count;

            if ($participant->score >= 50) {

                $participant->rank = '🥇 Gold';

            } elseif ($participant->score >= 30) {

                $participant->rank = '🥈 Silver';

            } elseif ($participant->score >= 15) {

                $participant->rank = '🥉 Bronze';

            } else {

                $participant->rank = '⭐ Beginner';

            }
        }

        $highestScore = $participants->max('score');

if ($highestScore == 0) {
    $highestScore = 1;
}
        $participants = $participants->sortByDesc('score');

        return view('participation.index', compact(
            'participants',
            'highestScore'));
    }
}