<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueue extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $table = 'sync_queue';

    protected $fillable = [
        'action_uuid',
        'user_id',
        'action_type',
        'payload',
        'is_synced',
        'sync_status',
        'last_error',
        'synced_at',
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
