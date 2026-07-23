<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $this->userNotificationsQuery()->get()
            ->map(fn ($notification) => $this->formatNotification($notification));
        $unreadCount = $this->unreadCount();

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->values(),
                'unread_count' => $unreadCount,
            ]);
        }

        return view('notification.index', compact('notifications', 'unreadCount'));
    }

    public function poll(Request $request)
    {
        $afterId = (int) $request->query('after', 0);

        $query = Auth::user()
            ->notifications()
            ->with(['post.topic', 'quiz', 'user'])
            ->visible()
            ->where('Is_Read', false);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $notifications = $query->latest()->get()->map(fn ($notification) => $this->formatNotification($notification));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->unreadCount(),
            'latest_id' => (int) Auth::user()->notifications()->visible()->max('id'),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->with(['post.topic', 'quiz', 'user'])
            ->findOrFail($id);

        if (! $notification->Is_Read) {
            $notification->markAsRead();
        }

        $url = $this->notificationUrl($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'url' => $url,
                'unread_count' => $this->unreadCount(),
            ]);
        }

        return redirect($url);
    }

    private function userNotificationsQuery()
    {
        return Auth::user()
            ->notifications()
            ->visible()
            ->with(['post.topic.group', 'post.user', 'parentPost.user', 'quiz', 'group'])
            ->latest();
    }

    private function unreadCount(): int
    {
        return Auth::user()->notifications()->visible()->where('Is_Read', false)->count();
    }

    private function formatNotification($notification): array
    {
        $notification->loadMissing(['post.topic', 'group', 'quiz', 'user']);

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->Notification_Type,
            'is_read' => (bool) $notification->Is_Read,
            'time' => $notification->created_at?->diffForHumans(),
            'url' => $this->notificationUrl($notification),
            'icon' => $this->iconForType($notification->Notification_Type),
        ];
    }

    private function iconForType(?string $type): string
    {
        return match ($type) {
            'Quiz' => 'bi-patch-question-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'PostCreated' => 'bi-chat-left-text-fill',
            'reply' => 'bi-reply-fill',
            default => 'bi-bell-fill',
        };
    }

    private function notificationUrl($notification): string
    {
        $notification->loadMissing(['post.topic', 'group', 'quiz', 'user']);

        if ($notification->quiz_id) {
            return $notification->destinationUrl();
        }

        if ($notification->post?->topic) {
            $url = route('topics.show', $notification->post->topic);

            return $url.'#msg-'.$notification->post->id;
        }

        if ($notification->group_id && $notification->group) {
            return route('groups.show', $notification->group);
        }

        return route('notifications.index');
    }
}
