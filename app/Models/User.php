<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Topic;
use App\Models\Post;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;

#[Fillable(['Fname', 'Lname', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function canManageGroups(): bool
    {
        return $this->isLecturer() || $this->isAdmin();
    }

    public function canCreateTopics(): bool
    {
        return $this->isLecturer() || $this->isAdmin();
    }

    public function canJoinGroups(): bool
    {
        return $this->isStudent() || $this->isAdmin();
    }

    public function isMemberOf(Group $group): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $group->isMember($this->id);
    }

    public function createdGroups()
    {
        return $this->hasMany(Group::class, 'Created_By');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'User_ID', 'Group_ID')
            ->withTimestamps()
            ->withPivot('Member_Status');
    }

    public function groupMemberships()
    {
        return $this->hasMany(GroupMember::class, 'User_ID');
    }
}
