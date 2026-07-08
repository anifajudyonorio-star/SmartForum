<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Notification;

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
            Notification::create([
                'user_ID' => $parentPost->Created_By,
                'Post_ID' => $reply->id,
                'Notification_Type' => 'reply',
                'Notification_Title' => $sender,
                'Message' => 'Replied to your message',
                'Is_Read' => false,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
