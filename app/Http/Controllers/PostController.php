<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Topic;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\PostVisibilityService;
use App\Services\InactiveMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function create(Topic $topic)
    {
        $topic->loadMissing('group');

        abort_unless(
            $topic->group && Auth::user()->canParticipateInGroup($topic->group),
            403,
            'You must be an active member of this group to post.'
        );

        return view('posts.create', compact('topic'));
    }

    public function store(Request $request, Topic $topic)
    {
        $topic->loadMissing('group');

        abort_unless(
            $topic->group && Auth::user()->canParticipateInGroup($topic->group),
            403,
            'You must be an active member of this group to post in this topic.'
        );

        $request->validate([
            'Post_Content' => 'required|string',
            'excluded_users' => 'nullable|array',
            'excluded_users.*' => 'integer|exists:users,id',
        ]);

        $parentPostId = $request->filled('Parent_Post_ID') ? $request->Parent_Post_ID : null;

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Parent_Post_ID' => $parentPostId,
            'Post_Content' => $request->Post_Content,
            'Created_By' => Auth::id(),
        ]);

        PostVisibilityService::syncHiddenFrom(
            $post,
            $topic,
            $request->input('excluded_users', []),
            Auth::user()
        );

        $post->load('hiddenFromUsers');

        if ($topic->Created_By != Auth::id() && ! $post->hiddenFromUsers->contains('id', $topic->Created_By)) {
            try {
                Notification::create([
                    'user_ID' => $topic->Created_By,
                    'Notification_Type' => 'PostCreated',
                    'Notification_Title' => Auth::user()->name,
                    'Message' => 'Posted in "'.$topic->Title.'"',
                    'Is_Read' => false,
                    'Post_ID' => $post->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($parentPostId) {
            NotificationService::notifyReply($post);
        }

        if ($topic->group) {
            app(InactiveMemberService::class)->recordActivity($topic->group, Auth::user());
        }

        if ($request->wantsJson()) {
            $post->load(['user', 'parent.user']);

            return response()->json([
                'success' => true,
                'post' => $this->formatPostForChat($post),
            ]);
        }

        return redirect()
            ->route('topics.show', $topic)
            ->with('success', 'Message sent successfully.');
    }

    public function edit(Post $post)
    {
        abort_unless($this->canManagePost($post), 403);

        $post->load(['topic.group', 'hiddenFromUsers']);
        $groupMembers = PostVisibilityService::groupMembersExcept($post->topic, Auth::user());

        return view('posts.edit', compact('post', 'groupMembers'));
    }

    public function update(Request $request, Post $post)
    {
        abort_unless($this->canManagePost($post), 403);

        $request->validate([
            'Post_Content' => 'required|string',
            'excluded_users' => 'nullable|array',
            'excluded_users.*' => 'integer|exists:users,id',
        ]);

        $post->update([
            'Post_Content' => $request->Post_Content,
        ]);

        $post->loadMissing('topic.group');

        PostVisibilityService::syncHiddenFrom(
            $post,
            $post->topic,
            $request->input('excluded_users', []),
            Auth::user()
        );

        return redirect()
            ->route('topics.show', $post->topic)
            ->with('success', 'Message updated successfully.');
    }

    public function destroy(Post $post)
    {
        abort_unless($this->canManagePost($post), 403);

        $topic = $post->topic;

        $post->delete();

        return redirect()
            ->route('topics.show', $topic)
            ->with('success', 'Message deleted successfully.');
    }

    private function canManagePost(Post $post): bool
    {
        $user = Auth::user();
        $post->loadMissing('topic.group');

        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $post->Created_By === $user->id) {
            return true;
        }

        // Group admins can moderate posts in their group.
        return $post->topic?->group && $user->canManageGroup($post->topic->group);
    }

    private function formatPostForChat(Post $post): array
    {
        $post->loadMissing('topic');
        $parent = null;
        if ($post->parent && $post->parent->isVisibleTo(Auth::user())) {
            $parent = [
                'id' => $post->parent->id,
                'user_name' => $post->parent->user->name ?? 'User',
                'content' => $post->parent->Post_Content,
            ];
        }

        return [
            'id' => $post->id,
            'content' => $post->Post_Content,
            'created_at' => $post->created_at->format('g:i A'),
            'created_human' => $post->created_at->diffForHumans(),
            'user_name' => $post->user->name ?? 'User',
            'user_initials' => strtoupper(substr($post->user->name ?? 'U', 0, 2)),
            'is_mine' => (int) $post->Created_By === Auth::id(),
            'hidden_count' => $post->hiddenFromUsers()->count(),
            'parent' => $parent,
            'share_url' => route('topics.show', $post->topic).'#msg-'.$post->id,
            'topic_title' => $post->topic->Title ?? 'Discussion',
        ];
    }
}
