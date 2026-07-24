<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\ParticipationService;
use App\Services\StatisticsScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ParticipationController extends Controller
{
    public function __construct(private readonly ParticipationService $participation) {}

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

        [$participants, $highestScore, $settings] = $this->participation->buildParticipants(
            $user,
            $selectedGroup,
            $availableGroups
        );

        $canManage = $selectedGroup && $user->canManageGroup($selectedGroup);

        return view('participation.index', compact(
            'participants',
            'highestScore',
            'availableGroups',
            'selectedGroup',
            'settings',
            'canManage'
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
        [$participants, $highestScore, $settings] = $this->participation->buildParticipants(
            $user,
            $selectedGroup,
            $availableGroups
        );
        $canManage = $user->canManageGroup($group);

        return view('participation.index', compact(
            'participants',
            'highestScore',
            'availableGroups',
            'selectedGroup',
            'settings',
            'canManage'
        ));
    }

    public function updateSettings(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        $validated = $request->validate([
            'topic_points' => ['required', 'integer', 'min:0', 'max:100'],
            'post_points' => ['required', 'integer', 'min:0', 'max:100'],
            'reply_points' => ['required', 'integer', 'min:0', 'max:100'],
            'gold_min' => ['required', 'integer', 'min:1', 'max:1000'],
            'silver_min' => ['required', 'integer', 'min:1', 'max:1000'],
            'bronze_min' => ['required', 'integer', 'min:1', 'max:1000'],
            'manual_marks_max' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->participation->updateSettings($group, $validated);

        return back()->with('success', 'Participation criteria updated for this group.');
    }

    public function updateGrade(Request $request, Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless($group->isMember($user->id), 404);

        $validated = $request->validate([
            'manual_marks' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->participation->updateManualGrade(
            $group,
            $user,
            Auth::user(),
            (int) $validated['manual_marks'],
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Participation marks saved for '.$user->name.'.');
    }

    private function availableGroupsFor(User $user): Collection
    {
        return StatisticsScopeService::participationGroupsFor($user);
    }
}
