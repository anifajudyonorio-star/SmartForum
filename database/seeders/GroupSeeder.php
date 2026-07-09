<?php

namespace Database\Seeders;

use App\Models\Group;
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
                'Description' => 'Demo discussion group for SmartForum. Admins create groups and assign members; lecturers create topics.',
                'Created_By' => $admin->id,
                'Status' => 'Active',
            ]
        );

        foreach ([$lecturer, $student] as $member) {
            if (! $group->isMember($member->id)) {
                $group->members()->attach($member->id, [
                    'Member_Status' => 'Active',
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
