<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_id',
        'last_sync',
        'is_online',
    ];

    protected $casts = [
        'last_sync' => 'datetime',
        'is_online' => 'boolean',
    ];

    /**
     * A device belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}