<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'Group_Name' => fake()->words(3, true),
            'Description' => fake()->sentence(),
            'Created_By' => User::factory(),
            'Status' => 'Active',
        ];
    }
}
