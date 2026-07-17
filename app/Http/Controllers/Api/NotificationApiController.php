<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationApiController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->visible()
            ->with(['post.topic.group', 'post.user', 'parentPost.user', 'quiz', 'user'])
            ->latest()
            ->get()
            ->map(fn ($notification) => $this->formatNotification($notification));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Auth::user()->notifications()->visible()->where('Is_Read', false)->count(),
        ]);
    }

    public function poll(Request $request)
    {
        return app(NotificationController::class)->poll($request);
    }

    public function markAsRead(Request $request, $id)
    {
        return app(NotificationController::class)->markAsRead($request, $id);
    }

    private function formatNotification($notification): array
    {
        $notification->loadMissing(['post.topic', 'group', 'quiz', 'user']);

        $topicId = $notification->post?->topic?->id;

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->Notification_Type,
            'is_read' => (bool) $notification->Is_Read,
            'created_at' => $notification->created_at?->toIso8601String(),
            'time' => $notification->created_at?->diffForHumans(),
            'topic_id' => $topicId,
            'post_id' => $notification->post?->id,
            'group_id' => $notification->group_id,
            'quiz_id' => $notification->quiz_id,
            'url' => $notification->quiz_id
                ? $notification->destinationUrl()
                : ($notification->group
                    ? route('groups.show', $notification->group)
                    : $notification->destinationUrl()),
        ];
    }
}
