<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Topic;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function create(Topic $topic)
    {
        abort_unless(Auth::user()->isMemberOf($topic->group), 403);

        return view('posts.create', compact('topic'));
    }

    public function store(Request $request, Topic $topic)
    {
        $topic->loadMissing('group');

        abort_unless(
            $topic->group && Auth::user()->isMemberOf($topic->group),
            403,
            'Join the group to post in this topic.'
        );

        $request->validate([
            'Post_Content' => 'required|string',
        ]);

        $parentPostId = $request->filled('Parent_Post_ID') ? $request->Parent_Post_ID : null;

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Parent_Post_ID' => $parentPostId,
            'Post_Content' => $request->Post_Content,
            'Created_By' => Auth::id(),
        ]);

        if ($topic->Created_By != Auth::id()) {
            try {
                Notification::create([
                    'user_ID' => $topic->Created_By,
                    'Notification_Type' => 'PostCreated',
                    'Notification_Title' => 'New Discussion Message',
                    'Message' => Auth::user()->name.' posted in your topic "'.$topic->Title.'".',
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

        return redirect()
            ->route('topics.show', $topic)
            ->with('success', 'Message sent successfully.');
    }

    public function edit(Post $post)
    {
        abort_unless($this->canManagePost($post), 403);

        return view('posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        abort_unless($this->canManagePost($post), 403);

        $request->validate([
            'Post_Content' => 'required|string',
        ]);

        $post->update([
            'Post_Content' => $request->Post_Content,
        ]);

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

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $post->Created_By === $user->id;
    }
}
