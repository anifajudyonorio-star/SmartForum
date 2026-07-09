<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $myGroups = Group::withCount('topics')
                ->withCount('memberships as members_count')
                ->with('user')
                ->latest()
                ->get();
        } else {
            $myGroups = $user->groups()
                ->withCount('topics')
                ->with('user')
                ->latest()
                ->get();
        }

        return view('groups.index', compact('myGroups'));
    }

    public function create()
    {
        abort_unless(Auth::user()->canManageGroups(), 403, 'Only admins can create groups.');

        return view('groups.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->canManageGroups(), 403, 'Only admins can create groups.');

        $request->validate([
            'Group_Name' => 'required|max:255',
            'Description' => 'required',
        ]);

        Group::create([
            'Group_Name' => $request->Group_Name,
            'Description' => $request->Description,
            'Created_By' => Auth::id(),
            'Status' => 'Active',
        ]);

        return redirect()->route('groups.index')
            ->with('success', 'Group created successfully. Add members to open it for students and lecturers.');
    }

    public function show(Group $group)
    {
        $user = Auth::user();

        abort_unless(
            $user->canViewGroup($group),
            403,
            'You are not assigned to this group. Contact an admin to be added.'
        );

        $isMember = $user->isMemberOf($group);
        $canManage = $user->canManageGroups();

        $topics = $group->topics()->with('user')->latest()->get();

        $members = collect();
        $availableUsers = collect();

        if ($canManage) {
            $members = $group->members()->orderBy('Fname')->orderBy('Lname')->get();
            $availableUsers = User::query()
                ->whereIn('role', ['student', 'lecturer'])
                ->whereNotIn('id', $members->pluck('id'))
                ->orderBy('Fname')
                ->orderBy('Lname')
                ->get(['id', 'Fname', 'Lname', 'email', 'role']);
        }

        return view('groups.show', compact(
            'group',
            'topics',
            'isMember',
            'canManage',
            'members',
            'availableUsers'
        ));
    }

    public function addMember(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canManageGroups(), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $member = User::findOrFail($request->user_id);

        abort_unless(
            $member->isStudent() || $member->isLecturer(),
            422,
            'Only students and lecturers can be added to groups.'
        );

        if (! $group->isMember($member->id)) {
            $group->members()->attach($member->id, [
                'Member_Status' => 'Active',
            ]);
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $member->name.' was added to the group.');
    }

    public function removeMember(Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroups(), 403);

        if ($group->isMember($user->id)) {
            $group->members()->detach($user->id);
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('success', $user->name.' was removed from the group.');
    }

    public function edit(Group $group)
    {
        abort_unless($this->canManageGroup($group), 403);

        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        abort_unless($this->canManageGroup($group), 403);

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
        abort_unless($this->canManageGroup($group), 403);

        $group->delete();

        return redirect()->route('groups.index')
            ->with('success', 'Group deleted successfully.');
    }

    private function canManageGroup(Group $group): bool
    {
        return Auth::user()->isAdmin();
    }
}
