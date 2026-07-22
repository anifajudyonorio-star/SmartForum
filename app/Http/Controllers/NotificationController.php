<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->visible()
            ->with(['post.topic.group', 'post.user', 'parentPost.user', 'quiz'])
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
            ->with(['post.topic', 'quiz', 'user'])
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
                'quiz_id' => $notification->quiz_id,
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
            ->with(['post.topic', 'quiz', 'user'])
            ->findOrFail($id);

        $notification->markAsRead();

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
     * Group related notifications into threaded display groups.
     */
    private function groupForDisplay(Collection $notifications): Collection
    {
        $groups = [];

        foreach ($notifications as $notification) {
            if ($notification->Notification_Type === 'reply' && $notification->parent_post_id) {
                $key = 'reply_'.$notification->parent_post_id;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'type' => 'reply_thread',
                        'latest_at' => $notification->created_at,
                        'items' => [],
                    ];
                }

                $groups[$key]['items'][] = $notification;

                if ($notification->created_at->gt($groups[$key]['latest_at'])) {
                    $groups[$key]['latest_at'] = $notification->created_at;
                }
            } elseif ($notification->Notification_Type === 'PostCreated' && $notification->post?->Topic_ID) {
                $key = 'topic_'.$notification->post->Topic_ID;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'type' => 'topic_thread',
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
            if (in_array($group['type'], ['reply_thread', 'topic_thread'], true)) {
                usort($group['items'], fn ($a, $b) => $a->created_at <=> $b->created_at);
                $group = $this->enrichThreadGroup($group);
            }
        }
        unset($group);

        return collect($groups)
            ->sortByDesc(fn ($group) => $group['latest_at'])
            ->values();
    }

    private function enrichThreadGroup(array $group): array
    {
        $items = collect($group['items']);
        $first = $items->first();

        $group['count'] = $items->count();
        $group['unread_count'] = $items->where('is_read', false)->count();
        $group['has_unread'] = $group['unread_count'] > 0;

        if ($group['type'] === 'reply_thread') {
            $parent = $first?->parentPost;
            $topic = $first?->post?->topic;

            $group['heading'] = 'Replies to your message';
            $group['context'] = $topic?->Title;
            $group['context_url'] = $topic ? route('topics.show', $topic) : null;
            $group['quote'] = $parent ? Str::limit($parent->Post_Content, 100) : null;
            $group['icon'] = 'bi-reply-fill';
        } else {
            $topic = $first?->post?->topic;

            $group['heading'] = 'New posts in discussion';
            $group['context'] = $topic?->Title;
            $group['context_url'] = $topic ? route('topics.show', $topic) : null;
            $group['quote'] = $topic?->group?->Group_Name;
            $group['icon'] = 'bi-chat-left-text-fill';
        }

        return $group;
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
