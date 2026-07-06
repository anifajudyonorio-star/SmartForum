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

        $parentPost = Post::find($reply->Parent_Post_ID);

        if (! $parentPost || $parentPost->Created_By == $reply->Created_By) {
            return;
        }

        try {
            Notification::create([
                'user_ID' => $parentPost->Created_By,
                'Post_ID' => $reply->id,
                'Notification_Type' => 'reply',
                'Notification_Title' => 'New Reply',
                'Message' => 'Someone replied to your post.',
                'Is_Read' => false,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
