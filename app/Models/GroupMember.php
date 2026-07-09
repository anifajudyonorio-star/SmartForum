<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_LECTURER = 'lecturer';

    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_LECTURER,
        self::ROLE_MEMBER,
    ];

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_SUSPENDED = 'Suspended';

    public const STATUS_BLOCKED = 'Blocked';

    protected $table = 'group_members';

    protected $fillable = [
        'User_ID',
        'Group_ID',
        'Member_Status',
        'Member_Role',
        'warnings',
    ];

    protected function casts(): array
    {
        return [
            'warnings' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'User_ID');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'Group_ID');
    }

    public function isAdmin(): bool
    {
        return $this->Member_Role === self::ROLE_ADMIN;
    }

    public function isLecturer(): bool
    {
        return $this->Member_Role === self::ROLE_LECTURER;
    }

    public function isActive(): bool
    {
        return ($this->Member_Status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->Member_Status === self::STATUS_SUSPENDED;
    }

    public function isBlocked(): bool
    {
        return $this->Member_Status === self::STATUS_BLOCKED;
    }
}
