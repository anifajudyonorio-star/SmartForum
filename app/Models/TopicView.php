<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicView extends Model
{
    protected $table = 'topic_views';

    protected $fillable = [
        'user_id',
        'topic_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];
}
