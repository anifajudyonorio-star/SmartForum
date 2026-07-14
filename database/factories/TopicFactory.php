<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TopicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'Title' => fake()->sentence(4),
            'Topic_Description' => fake()->paragraph(),
            'Group_ID' => Group::factory(),
            'Created_By' => User::factory(),
        ];
    }
}
