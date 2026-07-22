<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'Topic_ID',
        'Parent_Post_ID',
        'Created_By',
        'post_title',
        'Post_Content',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'Topic_ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'Created_By');
    }

    // Reply relationship (self-referencing)
    public function parent()
    {
        return $this->belongsTo(Post::class, 'Parent_Post_ID');
    }

    public function replies()
    {
        return $this->hasMany(Post::class, 'Parent_Post_ID');
    }

    public function hiddenFromUsers()
    {
        return $this->belongsToMany(User::class, 'post_hidden_from', 'post_id', 'user_id')
            ->withTimestamps();
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'post_id');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->Created_By === $user->id) {
            return true;
        }

        if ($this->relationLoaded('hiddenFromUsers')) {
            return ! $this->hiddenFromUsers->contains('id', $user->id);
        }

        return ! $this->hiddenFromUsers()->where('users.id', $user->id)->exists();
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('Created_By', $user->id)
                ->orWhereDoesntHave('hiddenFromUsers', function ($sub) use ($user) {
                    $sub->where('users.id', $user->id);
                });
        });
    }
}