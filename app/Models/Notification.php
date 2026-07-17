<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notification extends Model
{
    protected $fillable = [
        'user_ID',
        'Notification_Type',
        'Notification_Title',
        'Message',
        'Is_Read',
        'Post_ID',
        'group_id',
        'parent_post_id',
        'reply_count',
        'quiz_id',
        'expires_at',
    ];

    protected $casts = [
        'Is_Read' => 'boolean',
        'reply_count' => 'integer',
    ];

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['Notification_Title'] ?? null;
    }

    public function getMessageAttribute(): ?string
    {
        $message = $this->attributes['Message'] ?? null;

        if ($this->Notification_Type === 'reply') {
            if ($message && preg_match('/^Reply #\d+/i', $message)) {
                $this->loadMissing('post.user');

                if ($this->post?->Post_Content) {
                    return Str::limit($this->post->Post_Content, 120);
                }

                $message = preg_replace('/^Reply #\d+\s*[—-]\s*/iu', '', $message);
                $message = preg_replace('/\d+\s+replies to your message\s*\(latest from [^)]+\)/iu', '', $message);
                $message = trim($message);
            }
        }

        if ($this->Notification_Type === 'Quiz' && $this->quiz) {
            $scheduledAt = $this->quiz->start_time?->format('M j, Y g:i A');
            $endsAt = $this->quiz->end_time?->format('M j, Y g:i A');

            if ($scheduledAt && $endsAt) {
                $title = $this->quiz->title
                    ? 'A new quiz "' . $this->quiz->title . '" is scheduled for ' . $scheduledAt . ' and closes on ' . $endsAt . '.'
                    : 'A new quiz is scheduled for ' . $scheduledAt . ' and closes on ' . $endsAt . '.';

                return blank($message) || $message === 'A new quiz is available.' ? $title : $message;
            }
        }

        return $message;
    }

    public function getIsReadAttribute(): bool
    {
        return (bool) ($this->attributes['Is_Read'] ?? false);
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'Post_ID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function parentPost()
    {
        return $this->belongsTo(Post::class, 'parent_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_ID');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function scopeVisible($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('quiz_id')
                ->orWhereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
