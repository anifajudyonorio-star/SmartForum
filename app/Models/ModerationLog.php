<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'action', 'reason'];
}
