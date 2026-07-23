<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Topic;
use App\Models\Post;
use App\Models\TopicView;
use App\Services\MachineLearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicController extends Controller
{
    public function create(Group $group)
    {
        abort_unless(
            Auth::user()->canParticipateInGroup($group),
            403,
            'You must be an active member of this group to create topics.'
        );

        return view('topics.create', compact('group'));
    }

    public function store(Request $request, Group $group)
    {
        abort_unless(
            Auth::user()->canParticipateInGroup($group),
            403,
            'You must be an active member of this group to create topics.'
        );

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

    public function apiShow(Topic $topic)
    {
        abort_unless(Auth::user()->canViewGroup($topic->group), 403);

        $topic->load('group');

        $this->recordTopicEngagementView($topic);

        $posts = Post::with(['user', 'parent.user', 'hiddenFromUsers'])
            ->where('Topic_ID', $topic->id)
            ->visibleTo(Auth::user())
            ->oldest()
            ->get()
            ->map(fn ($post) => app(\App\Http\Controllers\Api\PostApiController::class)->formatPost($post));

        return response()->json([
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->Title,
                'description' => $topic->Topic_Description,
                'group_name' => $topic->group->Group_Name ?? '',
            ],
            'posts' => $posts,
            'cached_at' => now()->toISOString(),
        ]);
    }

    public function postsFragment(Topic $topic)
    {
        abort_unless(
            Auth::user()->canViewGroup($topic->group),
            403
        );

        $posts = Post::with(['user', 'parent.user', 'hiddenFromUsers'])
            ->where('Topic_ID', $topic->id)
            ->visibleTo(Auth::user())
            ->oldest()
            ->get();

        return view('posts.fragment', compact('posts'));
    }

    public function show(Topic $topic)
    {
        abort_unless(
            Auth::user()->canViewGroup($topic->group),
            403,
            'You must be a member of this group to view and participate in this discussion.'
        );

        $topic->load('group');

        $this->recordTopicEngagementView($topic);

        $posts = Post::with(['user', 'parent.user', 'hiddenFromUsers'])
            ->where('Topic_ID', $topic->id)
            ->visibleTo(Auth::user())
            ->oldest()
            ->get();

        $groupMembers = \App\Services\PostVisibilityService::groupMembersExcept($topic, Auth::user());

        return view('topics.show', compact('topic', 'posts', 'groupMembers'));
    }

    private function recordTopicEngagementView(Topic $topic): void
    {
        TopicView::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'topic_id' => $topic->id,
            ],
            ['viewed_at' => now()]
        );
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

    public function search(Request $request, MachineLearningService $mlService)
    {
        $search = trim((string) $request->query('search', ''));
        $user = Auth::user();

        $groupIds = $user->viewableGroupIds();

        if ($groupIds->isEmpty()) {
            return view('topics.search', [
                'topics' => collect(),
                'search' => $search,
                'recommendedTopics' => collect(),
            ]);
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

        $topics = $query->latest()->get();
        $recommendedTopics = $this->loadRecommendedTopics($mlService, $user);

        return view('topics.search', compact('topics', 'search', 'recommendedTopics'));
    }

    public function index(MachineLearningService $mlService)
    {
        $user = Auth::user();
        $topics = Topic::with(['user', 'group'])
            ->withCount('posts')
            ->visibleToUser($user)
            ->latest()
            ->get();

        $recommendedTopics = $this->loadRecommendedTopics($mlService, $user);

        return view('topics.index', compact('topics', 'recommendedTopics'));
    }

    private function loadRecommendedTopics(MachineLearningService $mlService, $user)
    {
        $recommendations = collect($mlService->getRecommendations($user->id))
            ->filter(fn ($item) => isset($item['id']))
            ->values();

        $recommendedTopicIds = $recommendations->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($recommendedTopicIds === []) {
            return collect();
        }

        return Topic::with(['user', 'group'])
            ->whereIn('id', $recommendedTopicIds)
            ->whereHas('group')
            ->get()
            ->map(function ($topic) use ($recommendations, $user) {
                $match = $recommendations->firstWhere('id', (int) $topic->id);
                $topic->recommendation_score = $match['score'] ?? 0;

                if ($topic->group) {
                    $topic->can_view = $user->canViewGroup($topic->group);
                    $topic->join_status = $user->joinRequestStatus($topic->group);
                } else {
                    $topic->can_view = false;
                    $topic->join_status = 'none';
                }

                return $topic;
            })
            ->sortByDesc('recommendation_score')
            ->values();
    }

    private function canManageTopic(Topic $topic): bool
    {
        $user = Auth::user();
        $group = $topic->group;

        // System admin or group admin can manage any topic in the group.
        if ($user->canManageGroup($group)) {
            return true;
        }

        // Topic creator can manage their own topic.
        if ((int) $topic->Created_By === $user->id) {
            return true;
        }

        // Group lecturers can manage topics.
        return $user->isGroupLecturer($group);
    }
}
