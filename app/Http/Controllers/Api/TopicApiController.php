<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $groupIds = $user->viewableGroupIds();

        if ($groupIds->isEmpty()) {
            return response()->json(['topics' => []]);
        }

        $topics = Topic::with(['user', 'group'])
            ->withCount('posts')
            ->whereIn('Group_ID', $groupIds)
            ->latest()
            ->get()
            ->map(fn (Topic $topic) => $this->formatTopic($topic));

        return response()->json(['topics' => $topics]);
    }

    public function forGroup(Group $group)
    {
        abort_unless(Auth::user()->canViewGroup($group), 403);

        $topics = $group->topics()
            ->with('user')
            ->withCount('posts')
            ->latest()
            ->get()
            ->map(fn (Topic $topic) => $this->formatTopic($topic));

        return response()->json(['topics' => $topics]);
    }

    public function store(Request $request, Group $group)
    {
        abort_unless(Auth::user()->canParticipateInGroup($group), 403);

        $request->validate([
            'Title' => 'required|max:255',
            'Topic_Description' => 'required',
        ]);

        $topic = Topic::create([
            'Group_ID' => $group->id,
            'Title' => $request->Title,
            'Topic_Description' => $request->Topic_Description,
            'Created_By' => Auth::id(),
        ]);

        $topic->load('user')->loadCount('posts');

        return response()->json(['topic' => $this->formatTopic($topic)], 201);
    }

    public function search(Request $request)
    {
        $user = Auth::user();
        $search = trim((string) $request->query('search', ''));

        $groupIds = $user->viewableGroupIds();

        if ($groupIds->isEmpty()) {
            return response()->json(['topics' => []]);
        }

        $query = Topic::with(['user', 'group'])
            ->withCount('posts')
            ->whereIn('Group_ID', $groupIds);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('Title', 'like', '%'.$search.'%')
                    ->orWhere('Topic_Description', 'like', '%'.$search.'%');
            });
        }

        $topics = $query->latest()->get()->map(function (Topic $topic) {
            $formatted = $this->formatTopic($topic);
            $formatted['group_name'] = $topic->group->Group_Name ?? '';

            return $formatted;
        });

        return response()->json(['topics' => $topics]);
    }

    public function update(Request $request, Topic $topic)
    {
        $topic->loadMissing('group');

        abort_unless(
            $topic->group && Auth::user()->canViewGroup($topic->group),
            403
        );

        abort_unless($this->canManageTopic($topic), 403);

        $request->validate([
            'Title' => 'required|max:255',
            'Topic_Description' => 'required',
        ]);

        $topic->update([
            'Title' => $request->Title,
            'Topic_Description' => $request->Topic_Description,
        ]);

        $topic->load('user')->loadCount('posts');

        return response()->json(['topic' => $this->formatTopic($topic)]);
    }

    public function destroy(Topic $topic)
    {
        $topic->loadMissing('group');

        abort_unless(
            $topic->group && Auth::user()->canViewGroup($topic->group),
            403
        );

        abort_unless($this->canManageTopic($topic), 403);

        $topic->delete();

        return response()->json(['success' => true]);
    }

    public function formatTopic(Topic $topic): array
    {
        return [
            'id' => $topic->id,
            'group_id' => $topic->Group_ID,
            'title' => $topic->Title,
            'topic_description' => $topic->Topic_Description,
            'created_by' => $topic->Created_By,
            'author_name' => $topic->user->name ?? '',
            'posts_count' => $topic->posts_count ?? $topic->posts()->count(),
        ];
    }

    private function canManageTopic(Topic $topic): bool
    {
        $user = Auth::user();
        $group = $topic->group;

        if ($user->canManageGroup($group)) {
            return true;
        }

        if ((int) $topic->Created_By === $user->id) {
            return true;
        }

        return $user->isGroupLecturer($group);
    }
}
