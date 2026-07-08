<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->get();

        return view('notification.index', compact('notifications'));
    }

    public function poll(Request $request)
    {
        $afterId = (int) $request->query('after', 0);

        $query = Auth::user()
            ->notifications()
            ->with(['post.topic'])
            ->where('Is_Read', false);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $notifications = $query->latest()->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->Notification_Type,
                'url' => $this->notificationUrl($notification),
                'time' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Auth::user()->notifications()->where('Is_Read', false)->count(),
            'latest_id' => (int) Auth::user()->notifications()->max('id'),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->with(['post.topic'])
            ->findOrFail($id);

        $notification->update([
            'Is_Read' => true,
        ]);

        $url = $this->notificationUrl($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'url' => $url,
                'unread_count' => auth()->user()->notifications()->where('Is_Read', false)->count(),
            ]);
        }

        return redirect($url);
    }

    private function notificationUrl($notification): string
    {
        $notification->loadMissing('post.topic');

        if ($notification->post?->topic) {
            return route('topics.show', $notification->post->topic);
        }

        return route('notifications.index');
    }
}
