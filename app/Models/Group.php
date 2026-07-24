<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'Group_Name',
        'Description',
        'join_rules',
        'Created_By',
        'Status',
        'inactivity_monitoring_enabled',
        'inactivity_threshold_days',
        'inactivity_grace_days',
        'inactivity_blacklist_days',
    ];

    protected function casts(): array
    {
        return [
            'inactivity_monitoring_enabled' => 'boolean',
            'inactivity_threshold_days' => 'integer',
            'inactivity_grace_days' => 'integer',
            'inactivity_blacklist_days' => 'integer',
        ];
    }

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
            ->withPivot(['Member_Status', 'Member_Role', 'warnings', 'last_activity_at', 'inactive_warning_sent_at', 'suspended_until', 'rules_accepted_at']);
    }

    public function memberships()
    {
        return $this->hasMany(GroupMember::class, 'Group_ID');
    }

    public function isMember(int $userId): bool
    {
        return $this->memberships()
            ->where('User_ID', $userId)
            ->whereIn('Member_Status', GroupMember::APPROVED_STATUSES)
            ->exists();
    }

    public function hasPendingJoinRequest(int $userId): bool
    {
        return $this->memberStatus($userId) === GroupMember::STATUS_PENDING;
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

    public function isActive(): bool
    {
        return $this->Status === 'Active';
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
