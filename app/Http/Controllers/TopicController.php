<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Topic;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicController extends Controller
{
    public function create(Group $group)
    {
        abort_unless(Auth::user()->canCreateTopics(), 403, 'Only lecturers can create topics.');
        abort_unless(Auth::user()->isMemberOf($group), 403, 'You must be a member of this group.');

        return view('topics.create', compact('group'));
    }

    public function store(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canCreateTopics(), 403, 'Only lecturers can create topics.');
        abort_unless(Auth::user()->isMemberOf($group), 403, 'You must be a member of this group.');

        $request->validate([
            'Title' => 'required|max:255',
            'Topic_Description' => 'required',
        ]);

        Topic::create([
            'Group_ID' => $group->id,
            'Title' => $request->Title,
            'Topic_Description' => $request->Topic_Description,
            'Created_By' => Auth::id(),
        ]);

        return redirect()
            ->route('groups.show', $group)
            ->with('success', 'Topic created successfully.');
    }

    public function show(Topic $topic)
    {
        abort_unless(
            Auth::user()->isMemberOf($topic->group),
            403,
            'Join the group to view and participate in this discussion.'
        );

        $posts = Post::with([
                'user',
                'replies.user',
            ])
            ->where('Topic_ID', $topic->id)
            ->whereNull('Parent_Post_ID')
            ->oldest()
            ->get();

        return view('topics.show', compact('topic', 'posts'));
    }

    public function edit(Topic $topic)
    {
        abort_unless($this->canManageTopic($topic), 403);

        return view('topics.edit', compact('topic'));
    }

    public function update(Request $request, Topic $topic)
    {
        abort_unless($this->canManageTopic($topic), 403);

        $request->validate([
            'Title' => 'required|max:255',
            'Topic_Description' => 'required',
        ]);

        $topic->update([
            'Title' => $request->Title,
            'Topic_Description' => $request->Topic_Description,
        ]);

        return redirect()
            ->route('topics.show', $topic)
            ->with('success', 'Topic updated successfully.');
    }

    public function destroy(Topic $topic)
    {
        abort_unless($this->canManageTopic($topic), 403);

        $group = $topic->group;

        $topic->delete();

        return redirect()
            ->route('groups.show', $group)
            ->with('success', 'Topic deleted successfully.');
    }

    public function search(Request $request)
    {
        $groupIds = Auth::user()->groups()->pluck('groups.id');

        $topics = Topic::with(['user', 'group'])
            ->where('Title', 'like', '%'.$request->search.'%')
            ->whereIn('Group_ID', $groupIds)
            ->latest()
            ->get();

        return view('topics.search', compact('topics'));
    }

    public function index()
    {
        $groupIds = Auth::user()->groups()->pluck('groups.id');

        $topics = Topic::with(['user', 'group'])
            ->withCount('posts')
            ->whereIn('Group_ID', $groupIds)
            ->latest()
            ->get();

        return view('topics.index', compact('topics'));
    }

    private function canManageTopic(Topic $topic): bool
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isLecturer() && (int) $topic->Created_By === $user->id;
    }
}
