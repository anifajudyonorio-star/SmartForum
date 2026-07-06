<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
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
}