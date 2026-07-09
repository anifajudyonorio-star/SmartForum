<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'group_id',
        'action',
        'reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
