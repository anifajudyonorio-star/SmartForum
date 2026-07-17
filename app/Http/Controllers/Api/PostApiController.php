<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Services\NotificationService;
use App\Services\PostVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostApiController extends Controller
{
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
            'Parent_Post_ID' => 'nullable|integer|exists:posts,id',
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

        $post->load(['user', 'parent.user', 'hiddenFromUsers']);

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

        return response()->json([
            'success' => true,
            'post' => $this->formatPost($post),
        ], 201);
    }

    public function update(Request $request, Post $post)
    {
        $post->loadMissing('topic.group');

        abort_unless(
            $post->topic?->group && Auth::user()->canViewGroup($post->topic->group),
            403
        );

        abort_unless($this->canManagePost($post), 403);

        $request->validate([
            'Post_Content' => 'required|string',
            'excluded_users' => 'nullable|array',
            'excluded_users.*' => 'integer|exists:users,id',
        ]);

        $post->update([
            'Post_Content' => $request->Post_Content,
        ]);

        PostVisibilityService::syncHiddenFrom(
            $post,
            $post->topic,
            $request->input('excluded_users', []),
            Auth::user()
        );

        $post->load(['user', 'parent.user', 'hiddenFromUsers']);

        return response()->json(['post' => $this->formatPost($post)]);
    }

    public function destroy(Post $post)
    {
        $post->loadMissing('topic.group');

        abort_unless(
            $post->topic?->group && Auth::user()->canViewGroup($post->topic->group),
            403
        );

        abort_unless($this->canManagePost($post), 403);

        $post->delete();

        return response()->json(['success' => true]);
    }

    public function formatPost(Post $post): array
    {
        return [
            'id' => $post->id,
            'topic_id' => $post->Topic_ID,
            'parent_post_id' => $post->Parent_Post_ID,
            'post_content' => $post->Post_Content,
            'created_by' => $post->Created_By,
            'author_name' => $post->user->name ?? 'User',
            'created_at' => $post->created_at?->toIso8601String(),
            'hidden_from_user_ids' => $post->hiddenFromUsers()->pluck('users.id')->values()->all(),
        ];
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

        return $post->topic?->group && $user->canManageGroup($post->topic->group);
    }
}
