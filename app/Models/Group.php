<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            ->withPivot(['Member_Status', 'Member_Role', 'warnings']);
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

    public function membership(int $userId): ?GroupMember
    {
        return $this->memberships()
            ->where('User_ID', $userId)
            ->first();
    }

    public function memberRole(int $userId): ?string
    {
        return $this->membership($userId)?->Member_Role;
    }

    public function memberStatus(int $userId): ?string
    {
        return $this->membership($userId)?->Member_Status;
    }

    public function isActiveMember(int $userId): bool
    {
        $membership = $this->membership($userId);

        return $membership && $membership->isActive();
    }

    public function isGroupAdmin(int $userId): bool
    {
        return $this->memberRole($userId) === GroupMember::ROLE_ADMIN;
    }

    public function isGroupLecturer(int $userId): bool
    {
        return $this->memberRole($userId) === GroupMember::ROLE_LECTURER;
    }

    public function adminCount(): int
    {
        return $this->members()
            ->wherePivot('Member_Role', GroupMember::ROLE_ADMIN)
            ->count();
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
