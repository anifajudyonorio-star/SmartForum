<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupJoinService;
use App\Services\GroupStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GroupApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $groups = $user->viewableGroupsQuery()
            ->withCount('topics')
            ->withCount('memberships as members_count')
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'groups' => $groups->map(fn (Group $group) => $this->formatGroup($group, $user))->values(),
        ]);
    }

    public function explore()
    {
        $user = Auth::user();
        $groups = GroupJoinService::exploreGroups($user);

        return response()->json([
            'groups' => $groups->map(fn (Group $group) => array_merge(
                $this->formatGroup($group, $user),
                ['join_status' => $group->join_status ?? GroupJoinService::joinStatusFor($user, $group)]
            ))->values(),
        ]);
    }

    public function requestJoin(Request $request, Group $group)
    {
        GroupJoinService::requestJoin(Auth::user(), $group, $request->boolean('accepted_rules'));

        return response()->json(['success' => true], 201);
    }

    public function approveJoinRequest(Group $group, User $user)
    {
        GroupJoinService::approveJoinRequest($group, $user, Auth::user());

        return response()->json(['success' => true]);
    }

    public function rejectJoinRequest(Group $group, User $user)
    {
        GroupJoinService::rejectJoinRequest($group, $user, Auth::user());

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'Group_Name' => 'required|max:255',
            'Description' => 'required',
        ]);

        $group = Group::create([
            'Group_Name' => $request->Group_Name,
            'Description' => $request->Description,
            'Created_By' => Auth::id(),
            'Status' => 'Active',
        ]);

        $group->members()->attach(Auth::id(), [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $group->loadCount(['topics', 'memberships as members_count'])->load('user');

        return response()->json([
            'group' => $this->formatGroup($group, Auth::user()),
        ], 201);
    }

    public function show(Group $group)
    {
        $user = Auth::user();

        abort_unless($user->canViewGroup($group), 403);

        $group->loadCount(['topics', 'memberships as members_count'])->load('user');
        $topics = $group->topics()->with('user')->latest()->get();
        $members = $group->members()
            ->wherePivot('Member_Status', '!=', GroupMember::STATUS_PENDING)
            ->orderBy('Fname')
            ->orderBy('Lname')
            ->get();
        $pendingJoinRequests = GroupJoinService::pendingRequestsFor($group);
        $availableUsers = collect();

        if ($user->canManageGroup($group)) {
            $availableUsers = User::query()
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', $members->pluck('id'))
                ->when(! $user->isAdmin(), function ($query) {
                    $query->whereIn('role', ['student', 'lecturer']);
                })
                ->orderBy('Fname')
                ->orderBy('Lname')
                ->get(['id', 'Fname', 'Lname', 'email', 'role']);
        }

        return response()->json([
            'group' => $this->formatGroup($group, $user),
            'members' => $members->map(fn (User $member) => $this->formatMember($member, $group->Created_By))->values(),
            'topics' => $topics->map(fn ($topic) => app(TopicApiController::class)->formatTopic($topic))->values(),
            'stats' => GroupStatisticsService::overviewStats($members, $topics),
            'available_users' => $availableUsers->map(fn (User $available) => [
                'id' => $available->id,
                'Fname' => $available->Fname,
                'Lname' => $available->Lname,
                'email' => $available->email,
                'role' => $available->role,
            ])->values(),
            'can_manage' => $user->canManageGroup($group),
            'can_participate' => $user->canParticipateInGroup($group),
            'is_member' => $user->isMemberOf($group),
            'pending_join_requests' => $pendingJoinRequests->map(fn (User $requester) => [
                'user_id' => $requester->id,
                'name' => $requester->name,
                'email' => $requester->email,
            ])->values(),
        ]);
    }

    public function addMember(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'Member_Role' => ['nullable', Rule::in(GroupMember::ROLES)],
        ]);

        $member = User::findOrFail($request->user_id);
        $role = $request->input('Member_Role', GroupMember::ROLE_MEMBER);

        if (! $group->isMember($member->id)) {
            $group->members()->attach($member->id, [
                'Member_Status' => GroupMember::STATUS_ACTIVE,
                'Member_Role' => $role,
                'warnings' => 0,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function removeMember(Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless($group->isMember($user->id), 404);

        if ($group->isGroupAdmin($user->id) && $group->adminCount() <= 1) {
            return response()->json(['message' => 'Cannot remove the last group admin.'], 422);
        }

        $group->members()->detach($user->id);

        return response()->json(['success' => true]);
    }

    public function updateMemberRole(Request $request, Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless($group->isMember($user->id), 404);

        $request->validate([
            'Member_Role' => ['required', Rule::in(GroupMember::ROLES)],
        ]);

        $newRole = $request->Member_Role;
        $currentRole = $group->memberRole($user->id);

        if (
            $currentRole === GroupMember::ROLE_ADMIN
            && $newRole !== GroupMember::ROLE_ADMIN
            && $group->adminCount() <= 1
        ) {
            return response()->json(['message' => 'Cannot demote the last group admin.'], 422);
        }

        $group->members()->updateExistingPivot($user->id, [
            'Member_Role' => $newRole,
        ]);

        return response()->json(['success' => true]);
    }

    private function formatGroup(Group $group, User $user): array
    {
        return [
            'id' => $group->id,
            'name' => $group->Group_Name,
            'description' => $group->Description,
            'join_rules' => $group->join_rules,
            'join_status' => GroupJoinService::joinStatusFor($user, $group),
            'status' => $group->Status,
            'created_by' => $group->Created_By,
            'creator_name' => $group->user->name ?? '',
            'topics_count' => $group->topics_count ?? $group->topics()->count(),
            'members_count' => $group->members_count ?? $group->members()->count(),
            'my_role' => $user->groupRole($group) ?? 'member',
        ];
    }

    private function formatMember(User $member, int $createdBy): array
    {
        return [
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'member_role' => $member->pivot->Member_Role ?? GroupMember::ROLE_MEMBER,
            'member_status' => $member->pivot->Member_Status ?? GroupMember::STATUS_ACTIVE,
            'warnings' => (int) ($member->pivot->warnings ?? 0),
            'is_creator' => (int) $member->id === (int) $createdBy,
        ];
    }
}
