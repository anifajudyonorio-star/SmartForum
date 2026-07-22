<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_RESTORED = 'restored';

    protected $fillable = [
        'post_id',
        'reporter_id',
        'group_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id')->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
