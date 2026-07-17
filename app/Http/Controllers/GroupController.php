<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupJoinService;
use App\Services\GroupStatisticsService;
use App\Services\QuizNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function __construct(private readonly QuizNotificationService $quizNotifications) {}

    public function index()
    {
        $user = Auth::user();

        $myGroups = $user->viewableGroupsQuery()
            ->withCount('topics')
            ->withCount('memberships as members_count')
            ->with('user')
            ->latest()
            ->get();

        return view('groups.index', compact('myGroups'));
    }

    public function explore()
    {
        $exploreGroups = GroupJoinService::exploreGroups(Auth::user());

        return view('groups.explore', compact('exploreGroups'));
    }

    public function requestJoin(Group $group)
    {
        try {
            GroupJoinService::requestJoin(Auth::user(), $group);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('groups.explore')
                ->with('error', $e->validator->errors()->first('group'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('groups.explore')
                ->with('error', 'Could not send your join request. Please try again or contact support.');
        }

        return redirect()
            ->route('groups.explore')
            ->with('success', 'Your request to join "'.$group->Group_Name.'" was sent. A group admin will review it.');
    }

    public function approveJoinRequest(Group $group, User $user)
    {
        GroupJoinService::approveJoinRequest($group, $user, Auth::user());

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $user->name.' was approved and added to the group.');
    }

    public function rejectJoinRequest(Group $group, User $user)
    {
        GroupJoinService::rejectJoinRequest($group, $user, Auth::user());

        return redirect()
            ->route('groups.show', $group)
            ->with('success', 'The join request from '.$user->name.' was declined.');
    }

    public function create()
    {
        return view('groups.create');
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

        // Creator becomes the group admin (WhatsApp-style).
        $group->members()->attach(Auth::id(), [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Group created successfully. You are the group admin — add members and assign roles.');
    }

    public function show(Group $group)
    {
        $user = Auth::user();

        abort_unless(
            $user->canViewGroup($group),
            403,
            'You are not a member of this group.'
        );

        $isMember = $user->isMemberOf($group);
        $canManage = $user->canManageGroup($group);
        $groupRole = $user->groupRole($group);

        $topics = $group->topics()->with('user')->latest()->get();

        // All group members (and system admins) can see who is in the group.
        $members = $group->members()
            ->wherePivot('Member_Status', '!=', GroupMember::STATUS_PENDING)
            ->orderBy('Fname')
            ->orderBy('Lname')
            ->get();
        $pendingJoinRequests = GroupJoinService::pendingRequestsFor($group);
        $availableUsers = collect();

        if ($canManage) {
            $availableUsers = User::query()
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', $members->pluck('id'))
                ->when(! $user->isAdmin(), function ($query) {
                    // Non-system-admins can only add students/lecturers (not other system admins).
                    $query->whereIn('role', ['student', 'lecturer']);
                })
                ->orderBy('Fname')
                ->orderBy('Lname')
                ->get(['id', 'Fname', 'Lname', 'email', 'role']);
        }

        $groupStats = GroupStatisticsService::overviewStats($members, $topics);

        return view('groups.show', compact(
            'group',
            'topics',
            'isMember',
            'canManage',
            'groupRole',
            'members',
            'availableUsers',
            'groupStats',
            'pendingJoinRequests'
        ));
    }

    public function addMember(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403, 'Only group admins can add members.');

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

            $this->quizNotifications->notifyNewlyActiveMember($member, $group);
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $member->name.' was added to the group as '.ucfirst($role).'.');
    }

    public function removeMember(Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403, 'Only group admins can remove members.');

        abort_unless($group->isMember($user->id), 404, 'User is not a member of this group.');

        // Prevent removing the last group admin.
        if ($group->isGroupAdmin($user->id) && $group->adminCount() <= 1) {
            return redirect()
                ->route('groups.show', $group)
                ->with('error', 'Cannot remove the last group admin. Assign another admin first.');
        }

        $group->members()->detach($user->id);

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $user->name.' was removed from the group.');
    }

    public function updateMemberRole(Request $request, Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403, 'Only group admins can change member roles.');

        abort_unless($group->isMember($user->id), 404, 'User is not a member of this group.');

        $request->validate([
            'Member_Role' => ['required', Rule::in(GroupMember::ROLES)],
        ]);

        $newRole = $request->Member_Role;
        $currentRole = $group->memberRole($user->id);

        // Prevent demoting the last group admin.
        if (
            $currentRole === GroupMember::ROLE_ADMIN
            && $newRole !== GroupMember::ROLE_ADMIN
            && $group->adminCount() <= 1
        ) {
            return redirect()
                ->route('groups.show', $group)
                ->with('error', 'Cannot demote the last group admin. Promote another member to admin first.');
        }

        $group->members()->updateExistingPivot($user->id, [
            'Member_Role' => $newRole,
        ]);

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $user->name."'s group role was updated to ".ucfirst($newRole).'.');
    }

    public function edit(Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        $request->validate([
            'Group_Name' => 'required|max:255',
            'Description' => 'required',
        ]);

        $group->update([
            'Group_Name' => $request->Group_Name,
            'Description' => $request->Description,
        ]);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        $group->delete();

        return redirect()->route('groups.index')
            ->with('success', 'Group deleted successfully.');
    }
}
