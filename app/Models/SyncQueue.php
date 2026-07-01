<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueue extends Model
{
    protected $table = 'sync_queue';

    protected $fillable = [
        'user_id',
        'action_type',
        'payload',
        'is_synced',
        'synced_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'is_synced' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
