<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\ParticipationService;
use App\Services\StatisticsScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ParticipationApiController extends Controller
{
    public function __construct(private readonly ParticipationService $participation) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->canViewParticipation(), 403);

        $availableGroups = StatisticsScopeService::participationGroupsFor($user);

        $groupId = $request->query('group');
        $selectedGroup = null;

        if ($groupId) {
            $selectedGroup = $availableGroups->firstWhere('id', (int) $groupId);
            abort_unless($selectedGroup, 403);
        } elseif ($availableGroups->count() === 1) {
            $selectedGroup = $availableGroups->first();
        }

        [$participants, $highestScore, $settings] = $this->participation->buildParticipants(
            $user,
            $selectedGroup,
            $availableGroups
        );

        return response()->json([
            'participants' => $participants
                ->map(fn ($participant) => $this->participation->formatParticipant($participant, $settings))
                ->values(),
            'highest_score' => $highestScore,
            'criteria' => $this->participation->formatSettings($settings),
            'available_groups' => $availableGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->Group_Name,
            ])->values(),
            'selected_group' => $selectedGroup ? [
                'id' => $selectedGroup->id,
                'name' => $selectedGroup->Group_Name,
            ] : null,
            'can_manage' => $selectedGroup ? $user->canManageGroup($selectedGroup) : false,
        ]);
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

        $settings = $this->participation->updateSettings($group, $validated);

        return response()->json([
            'message' => 'Participation criteria updated.',
            'criteria' => $this->participation->formatSettings($settings),
        ]);
    }

    public function updateGrade(Request $request, Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless($group->isMember($user->id), 404);

        $validated = $request->validate([
            'manual_marks' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $grade = $this->participation->updateManualGrade(
            $group,
            $user,
            Auth::user(),
            (int) $validated['manual_marks'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Participation marks saved.',
            'grade' => [
                'user_id' => $grade->user_id,
                'manual_marks' => $grade->manual_marks,
                'notes' => $grade->notes,
            ],
        ]);
    }
}
