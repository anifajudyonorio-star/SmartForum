<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ParticipationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->canViewParticipation(), 403, 'You do not have permission to access this page.');

        $availableGroups = $this->availableGroupsFor($user);

        $groupId = $request->query('group');
        $selectedGroup = null;

        if ($groupId) {
            $selectedGroup = $availableGroups->firstWhere('id', (int) $groupId);
            abort_unless($selectedGroup, 403, 'You cannot view participation for this group.');
        } elseif ($availableGroups->count() === 1) {
            $selectedGroup = $availableGroups->first();
        }

        [$participants, $highestScore] = $this->buildParticipants($user, $selectedGroup, $availableGroups);

        return view('participation.index', compact(
            'participants',
            'highestScore',
            'availableGroups',
            'selectedGroup'
        ));
    }

    public function group(Group $group)
    {
        $user = Auth::user();

        abort_unless(
            $user->canManageGroup($group) || ($user->isLecturer() && $user->isMemberOf($group)),
            403,
            'You do not have permission to view participation for this group.'
        );

        $availableGroups = collect([$group]);
        $selectedGroup = $group;
        [$participants, $highestScore] = $this->buildParticipants($user, $selectedGroup, $availableGroups);

        return view('participation.index', compact(
            'participants',
            'highestScore',
            'availableGroups',
            'selectedGroup'
        ));
    }

    private function availableGroupsFor(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Group::orderBy('Group_Name')->get();
        }

        $groups = $user->administeredGroups()->orderBy('Group_Name')->get();

        if ($user->isLecturer()) {
            $groups = $groups
                ->merge($user->groups()->orderBy('Group_Name')->get())
                ->unique('id')
                ->sortBy('Group_Name')
                ->values();
        }

        return $groups;
    }

    /**
     * @return array{0: Collection, 1: int}
     */
    private function buildParticipants(User $viewer, ?Group $selectedGroup, Collection $availableGroups): array
    {
        if ($selectedGroup) {
            $group = $selectedGroup;
            $topicIds = $group->topics()->pluck('id');

            $participants = $group->members()
                ->withCount([
                    'topics as topics_count' => fn ($q) => $q->where('Group_ID', $group->id),
                    'posts as posts_count' => fn ($q) => $q->whereIn('Topic_ID', $topicIds),
                    'posts as replies_count' => function ($q) use ($topicIds) {
                        $q->whereIn('Topic_ID', $topicIds)->whereNotNull('Parent_Post_ID');
                    },
                ])
                ->get();
        } else {
            $groupIds = $availableGroups->pluck('id');

            $query = User::query()->where(function ($q) {
                $q->whereNull('role')->orWhere('role', 'student');
            });

            if ($groupIds->isNotEmpty()) {
                $query->whereHas('groups', function ($q) use ($groupIds) {
                    $q->whereIn('groups.id', $groupIds);
                });
            } elseif (! $viewer->isAdmin()) {
                $query->whereRaw('1 = 0');
            }

            $participants = $query->withCount([
                'topics',
                'posts',
                'posts as replies_count' => function ($q) {
                    $q->whereNotNull('Parent_Post_ID');
                },
            ])->get();
        }

        foreach ($participants as $participant) {
            $participant->score =
                $participant->topics_count +
                $participant->posts_count +
                $participant->replies_count;

            $participant->rank = match (true) {
                $participant->score >= 50 => '🥇 Gold',
                $participant->score >= 30 => '🥈 Silver',
                $participant->score >= 15 => '🥉 Bronze',
                default => '⭐ Beginner',
            };
        }

        $highestScore = max(1, (int) $participants->max('score'));

        return [$participants->sortByDesc('score'), $highestScore];
    }
}
