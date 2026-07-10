<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->visible()
            ->with(['post', 'quiz'])
            ->latest()
            ->get();

        $notificationGroups = $this->groupForDisplay($notifications);

        return view('notification.index', compact('notificationGroups'));
    }

    public function poll(Request $request)
    {
        $afterId = (int) $request->query('after', 0);

        $query = Auth::user()
            ->notifications()
            ->with(['post.topic'])
            ->visible()
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
                'parent_post_id' => $notification->parent_post_id,
                'url' => $this->notificationUrl($notification),
                'time' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Auth::user()->notifications()->visible()->where('Is_Read', false)->count(),
            'latest_id' => (int) Auth::user()->notifications()->visible()->max('id'),
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
                'unread_count' => auth()->user()->notifications()->visible()->where('Is_Read', false)->count(),
            ]);
        }

        return redirect($url);
    }

    /**
     * Group reply notifications to the same post for stacked display.
     */
    private function groupForDisplay(Collection $notifications): Collection
    {
        $groups = [];

        foreach ($notifications as $notification) {
            if ($notification->Notification_Type === 'reply' && $notification->parent_post_id) {
                $key = 'reply_'.$notification->parent_post_id;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'type' => 'reply_stack',
                        'latest_at' => $notification->created_at,
                        'items' => [],
                    ];
                }

                $groups[$key]['items'][] = $notification;

                if ($notification->created_at->gt($groups[$key]['latest_at'])) {
                    $groups[$key]['latest_at'] = $notification->created_at;
                }
            } else {
                $groups['single_'.$notification->id] = [
                    'type' => 'single',
                    'latest_at' => $notification->created_at,
                    'item' => $notification,
                ];
            }
        }

        foreach ($groups as &$group) {
            if ($group['type'] === 'reply_stack') {
                usort($group['items'], fn ($a, $b) => $a->created_at <=> $b->created_at);
            }
        }
        unset($group);

        return collect($groups)
            ->sortByDesc(fn ($group) => $group['latest_at'])
            ->values();
    }

    private function notificationUrl($notification): string
    {
        $notification->loadMissing('post.topic');

        if ($notification->post?->topic) {
            $url = route('topics.show', $notification->post->topic);

            return $url.'#msg-'.$notification->post->id;
        }

        return route('notifications.index');
    }
}
