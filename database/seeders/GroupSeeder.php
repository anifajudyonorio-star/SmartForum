<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@smartforum.com')->first();
        $lecturer = User::where('email', 'lecturer@smartforum.com')->first();
        $student = User::where('email', 'student@smartforum.com')->first();

        if (! $admin || ! $lecturer || ! $student) {
            return;
        }

        $group = Group::updateOrCreate(
            ['Group_Name' => 'Introduction to Computer Science'],
            [
                'Description' => 'Demo discussion group for SmartForum. Anyone can create groups; the creator is admin and can assign roles.',
                'Created_By' => $admin->id,
                'Status' => 'Active',
            ]
        );

        $memberships = [
            $admin->id => GroupMember::ROLE_ADMIN,
            $lecturer->id => GroupMember::ROLE_LECTURER,
            $student->id => GroupMember::ROLE_MEMBER,
        ];

        foreach ($memberships as $userId => $role) {
            if ($group->isMember($userId)) {
                $group->members()->updateExistingPivot($userId, [
                    'Member_Status' => GroupMember::STATUS_ACTIVE,
                    'Member_Role' => $role,
                    'warnings' => 0,
                ]);
            } else {
                $group->members()->attach($userId, [
                    'Member_Status' => GroupMember::STATUS_ACTIVE,
                    'Member_Role' => $role,
                    'warnings' => 0,
                ]);
            }
        }

        Topic::updateOrCreate(
            [
                'Group_ID' => $group->id,
                'Title' => 'Welcome & Course Overview',
            ],
            [
                'Topic_Description' => 'Introduce yourself and review the course structure for this semester.',
                'Created_By' => $lecturer->id,
            ]
        );
    }
}
