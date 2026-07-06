<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Topic;
use App\Models\GroupMember;
use App\Models\User;

class Group extends Model
{
    protected $fillable = [
        'Group_Name',
        'Description',
        'Created_By',
        'Status',
    ];

    public function topics()
    {
        return $this->hasMany(Topic::class, 'Group_ID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'Created_By');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members', 'Group_ID', 'User_ID')
            ->withTimestamps()
            ->withPivot('Member_Status');
    }

    public function memberships()
    {
        return $this->hasMany(GroupMember::class, 'Group_ID');
    }

    public function isMember(int $userId): bool
    {
        return $this->members()
            ->where('users.id', $userId)
            ->exists();
    }

    public function scopeByLecturers($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->whereIn('role', ['lecturer', 'admin']);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('Status', 'Active');
    }
}
