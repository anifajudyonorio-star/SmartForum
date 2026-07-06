<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;

class GroupMember extends Model
{
    protected $table = 'group_members';

    protected $fillable = [
        'User_ID',
        'Group_ID',
        'Member_Status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'User_ID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'Group_ID');
    }
}
