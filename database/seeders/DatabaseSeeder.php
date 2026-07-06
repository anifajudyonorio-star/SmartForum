<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'Fname' => 'Test',
                'Lname' => 'Student',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => 'student',
            ],
            [
                'Fname' => 'Demo',
                'Lname' => 'Student',
                'email' => 'student@smartforum.com',
                'password' => Hash::make('password'),
                'role' => 'student',
            ],
            [
                'Fname' => 'Demo',
                'Lname' => 'Lecturer',
                'email' => 'lecturer@smartforum.com',
                'password' => Hash::make('password'),
                'role' => 'lecturer',
            ],
            [
                'Fname' => 'Super',
                'Lname' => 'Admin',
                'email' => 'admin@smartforum.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
