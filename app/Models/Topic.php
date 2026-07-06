<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;
use App\Models\Post;

class Topic extends Model
{
    protected $fillable = [
        'Title',
        'Topic_Description',
        'Description',
        'Group_ID',
        'Created_By',
    ];

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['title'] ?? $this->attributes['Title'] ?? null;
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value;
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'Group_ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'Created_By');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'Topic_ID');
    }
}
