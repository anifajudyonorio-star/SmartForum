<?php

use App\Models\Notification;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $legacyNotifications = Notification::query()
            ->where('Notification_Type', 'reply')
            ->where(function ($query) {
                $query->where('Message', 'like', 'Reply #%')
                    ->orWhere('reply_count', '>', 1);
            })
            ->get();

        foreach ($legacyNotifications as $notification) {
            if (! $notification->parent_post_id) {
                continue;
            }

            $replies = Post::query()
                ->with('user')
                ->where('Parent_Post_ID', $notification->parent_post_id)
                ->orderBy('created_at')
                ->get();

            if ($replies->isEmpty()) {
                $notification->delete();

                continue;
            }

            $recipientId = $notification->user_ID;
            $wasRead = $notification->Is_Read;

            foreach ($replies as $reply) {
                Notification::updateOrCreate(
                    [
                        'user_ID' => $recipientId,
                        'Post_ID' => $reply->id,
                    ],
                    [
                        'parent_post_id' => $notification->parent_post_id,
                        'Notification_Type' => 'reply',
                        'Notification_Title' => $reply->user->name ?? 'Someone',
                        'Message' => Str::limit($reply->Post_Content, 120),
                        'Is_Read' => $wasRead,
                        'reply_count' => 1,
                        'created_at' => $reply->created_at,
                        'updated_at' => $reply->updated_at,
                    ]
                );
            }

            $notification->delete();
        }
    }

    public function down(): void
    {
        // Legacy merged notifications cannot be restored reliably.
    }
};
