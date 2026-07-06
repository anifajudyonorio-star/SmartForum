<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $myGroups = $user->groups()
            ->withCount('topics')
            ->with('user')
            ->latest()
            ->get();

        $exploreGroups = collect();

        if ($user->canJoinGroups()) {
            $joinedIds = $myGroups->pluck('id');

            $exploreGroups = Group::withCount('topics')
                ->with('user')
                ->active()
                ->byLecturers()
                ->whereNotIn('id', $joinedIds)
                ->latest()
                ->get();
        }

        return view('groups.index', compact('myGroups', 'exploreGroups'));
    }

    public function create()
    {
        abort_unless(Auth::user()->canManageGroups(), 403, 'Only lecturers can create groups.');

        return view('groups.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->canManageGroups(), 403, 'Only lecturers can create groups.');

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
            'Member_Status' => 'Active',
        ]);

        return redirect()->route('groups.index')
            ->with('success', 'Group created successfully.');
    }

    public function show(Group $group)
    {
        $user = Auth::user();
        $isMember = $user->isMemberOf($group);

        if (! $isMember && ! $user->isStudent()) {
            abort(403, 'You must join this group first.');
        }

        $topics = $isMember
            ? $group->topics()->with('user')->latest()->get()
            : $group->topics()->latest()->get(['id', 'Group_ID', 'Title', 'Topic_Description', 'Created_By', 'created_at']);

        $canJoin = $user->canJoinGroups()
            && ! $isMember
            && $group->user
            && ($group->user->isLecturer() || $group->user->isAdmin());

        return view('groups.show', compact('group', 'topics', 'isMember', 'canJoin'));
    }

    public function join(Group $group)
    {
        $user = Auth::user();

        abort_unless($user->canJoinGroups(), 403, 'Only students can join groups.');
        if ($user->isMemberOf($group)) {
            return redirect()->route('groups.show', $group);
        }
        abort_unless($group->Status === 'Active', 403, 'This group is not available.');
        abort_unless(
            $group->user && ($group->user->isLecturer() || $group->user->isAdmin()),
            403,
            'You can only join groups created by lecturers.'
        );

        $group->members()->attach($user->id, [
            'Member_Status' => 'Active',
        ]);

        return redirect()->route('groups.show', $group)
            ->with('success', 'You have joined the group. You can now browse topics and post.');
    }

    public function leave(Group $group)
    {
        $user = Auth::user();

        abort_unless($user->isStudent(), 403);
        abort_unless($user->isMemberOf($group), 403);

        $group->members()->detach($user->id);

        return redirect()->route('groups.index')
            ->with('success', 'You have left the group.');
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

        return redirect()->route('groups.index')
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
        $user = Auth::user();

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isLecturer() && (int) $group->Created_By === $user->id;
    }
}
