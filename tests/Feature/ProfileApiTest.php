<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'Fname' => 'Demo',
            'Lname' => 'Student',
            'email' => 'student@smartforum.com',
        ]);

        $this->patchJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@smartforum.com',
        ], $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonPath('user.Fname', 'Updated')
            ->assertJsonPath('user.Lname', 'Name')
            ->assertJsonPath('user.email', 'updated@smartforum.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'Fname' => 'Updated',
            'Lname' => 'Name',
            'email' => 'updated@smartforum.com',
        ]);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->putJson('/api/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ], $this->apiHeaders($user))
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $this->deleteJson('/api/profile', [
            'password' => 'secret-password',
        ], $this->apiHeaders($user))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
