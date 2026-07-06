<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_ID',
        'Notification_Type',
        'Notification_Title',
        'Message',
        'Is_Read',
        'Post_ID',
    ];

    protected $casts = [
        'Is_Read' => 'boolean',
    ];

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['Notification_Title'] ?? null;
    }

    public function getMessageAttribute(): ?string
    {
        return $this->attributes['Message'] ?? null;
    }

    public function getIsReadAttribute(): bool
    {
        return (bool) ($this->attributes['Is_Read'] ?? false);
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'Post_ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_ID');
    }
}
