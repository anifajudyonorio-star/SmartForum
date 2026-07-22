<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_notification_is_hidden_after_twenty_four_hours(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'reply',
            'Notification_Title' => 'Demo Student',
            'Message' => 'Thanks for your reply.',
            'Is_Read' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $notification->refresh();
        $this->assertTrue($notification->Is_Read);
        $this->assertNotNull($notification->expires_at);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Thanks for your reply.');

        $this->travel(25)->hours();

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertDontSee('Thanks for your reply.');
    }

    public function test_unread_notification_remains_visible_without_expiry(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'reply',
            'Notification_Title' => 'Demo Student',
            'Message' => 'Still waiting for you.',
            'Is_Read' => false,
        ]);

        $this->travel(48)->hours();

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Still waiting for you.');
    }

    public function test_mark_as_read_api_sets_expiry_and_updates_unread_count(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $notification = Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'reply',
            'Notification_Title' => 'Demo Student',
            'Message' => 'API read expiry test.',
            'Is_Read' => false,
        ]);

        $this->patchJson("/api/notifications/{$notification->id}/read", [], [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $notification->refresh();
        $this->assertTrue($notification->Is_Read);
        $this->assertTrue($notification->expires_at->greaterThan(now()->addHours(23)));
    }
}
