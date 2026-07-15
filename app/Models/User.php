<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['Fname', 'Lname', 'email', 'password', 'role', 'warnings', 'is_blacklisted', 'google_id', 'apple_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blacklisted' => 'boolean',
            'warnings' => 'integer',
        ];
    }

    public function topics()
    {
        return $this->hasMany(Topic::class, 'Created_By');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'Created_By');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_ID');
    }

    public function getNameAttribute(): string
    {
        return trim(($this->Fname ?? '') . ' ' . ($this->Lname ?? '')) ?: $this->email;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLecturer(): bool
    {
        return $this->role === 'lecturer';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student' || $this->role === null;
    }

    /** Any authenticated user can create groups (WhatsApp-style). */
    public function canManageGroups(): bool
    {
        return true;
    }

    public function canCreateGroups(): bool
    {
        return true;
    }

    /** @deprecated Use canCreateTopicsIn(Group) — kept for backward compatibility. */
    public function canCreateTopics(): bool
    {
        return true;
    }

    public function canCreateTopicsIn(Group $group): bool
    {
        return $this->canParticipateInGroup($group);
    }

    public function canJoinGroups(): bool
    {
        return true;
    }

    public function canViewGroup(Group $group): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isMemberOf($group)) {
            return false;
        }

        // Blocked members cannot access the group.
        return $group->memberStatus($this->id) !== GroupMember::STATUS_BLOCKED;
    }

    public function isMemberOf(Group $group): bool
    {
        return $group->isMember($this->id);
    }

    public function canParticipateInGroup(Group $group): bool
    {
        return $group->isActiveMember($this->id);
    }

    public function groupRole(Group $group): ?string
    {
        return $group->memberRole($this->id);
    }

    public function isGroupAdmin(Group $group): bool
    {
        return $this->isAdmin() || $group->isGroupAdmin($this->id);
    }

    public function isGroupLecturer(Group $group): bool
    {
        return $group->isGroupLecturer($this->id);
    }

    public function canManageGroup(Group $group): bool
    {
        return $this->isGroupAdmin($group);
    }

    public function administeredGroups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'User_ID', 'Group_ID')
            ->withTimestamps()
            ->withPivot(['Member_Status', 'Member_Role', 'warnings'])
            ->wherePivot('Member_Role', GroupMember::ROLE_ADMIN);
    }

    public function administersAnyGroup(): bool
    {
        return $this->isAdmin() || $this->administeredGroups()->exists();
    }

    public function canViewParticipation(): bool
    {
        return $this->isAdmin()
            || $this->isLecturer()
            || $this->administeredGroups()->exists();
    }

    public function canViewStatistics(): bool
    {
        return $this->isAdmin() || $this->administeredGroups()->exists();
    }

    public function createdGroups()
    {
        return $this->hasMany(Group::class, 'Created_By');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'User_ID', 'Group_ID')
            ->withTimestamps()
            ->withPivot(['Member_Status']);
    }

    public function groupMemberships()
    {
        return $this->hasMany(GroupMember::class, 'User_ID');
    }
}
