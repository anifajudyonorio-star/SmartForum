<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Post;
use App\Models\PushSubscription;
use Illuminate\Support\Str;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService
{
    public static function notifyReply(Post $reply): void
    {
        if ($reply->Parent_Post_ID == null) {
            return;
        }

        $parentPost = Post::with('user')->find($reply->Parent_Post_ID);

        if (! $parentPost || $parentPost->Created_By == $reply->Created_By) {
            return;
        }

        $reply->loadMissing('user', 'hiddenFromUsers');
        $sender = $reply->user->name ?? 'Someone';

        if ($reply->hiddenFromUsers->contains('id', $parentPost->Created_By)) {
            return;
        }

        try {
            $notification = Notification::create([
                'user_ID'            => $parentPost->Created_By,
                'Post_ID'            => $reply->id,
                'parent_post_id'     => $parentPost->id,
                'Notification_Type'  => 'reply',
                'Notification_Title' => $sender,
                'Message'            => Str::limit($reply->Post_Content, 120),
                'Is_Read'            => false,
            ]);

            static::sendPush($parentPost->Created_By, [
                'title' => $sender,
                'body'  => Str::limit($reply->Post_Content, 120),
                'url'   => route('notifications.read', $notification->id),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function sendPush(int $userId, array $payload): void
    {
        $vapidPublic  = config('webpush.vapid.public_key');
        $vapidPrivate = config('webpush.vapid.private_key');
        $vapidSubject = config('webpush.vapid.subject');

        if (! $vapidPublic || ! $vapidPrivate) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $vapidSubject,
                'publicKey'  => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_token],
                ]),
                json_encode($payload)
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
