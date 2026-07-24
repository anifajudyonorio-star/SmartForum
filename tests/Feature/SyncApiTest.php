<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsApi(User $user): string
    {
        $token = $user->createToken('test')->plainTextToken;

        return $token;
    }

    private function apiHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/sync/device')->assertUnauthorized();
    }

    public function test_device_can_be_registered(): void
    {
        $user = User::factory()->create();
        $token = $this->actingAsApi($user);

        $this->postJson('/api/sync/device', [
            'device_id' => 'browser-test-001',
            'device_name' => 'Test Browser',
            'device_type' => 'browser',
        ], $this->apiHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_actions_can_be_uploaded(): void
    {
        $user = User::factory()->create();
        $token = $this->actingAsApi($user);

        $this->postJson('/api/sync/upload', [
            'actions' => [
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000001',
                    'action_type' => 'create_post',
                    'payload' => ['topic_id' => 1, 'content' => 'hello'],
                ],
            ],
        ], $this->apiHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_sync_requires_registered_device(): void
    {
        $user = User::factory()->create();
        $token = $this->actingAsApi($user);

        $this->postJson('/api/sync', [
            'device_id' => 'nonexistent-device',
        ], $this->apiHeaders($token))
            ->assertNotFound();
    }

    public function test_sync_processes_create_post_action(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = $this->actingAsApi($user);

        $group = Group::factory()->create();
        GroupMember::create(['Group_ID' => $group->id, 'User_ID' => $user->id, 'Member_Status' => 'active', 'Member_Role' => 'member']);
        $topic = Topic::factory()->create(['Group_ID' => $group->id, 'Created_By' => $user->id]);

        // Register device
        $this->postJson('/api/sync/device', [
            'device_id' => 'browser-test-002',
            'device_name' => 'Test Browser',
        ], $this->apiHeaders($token));

        // Upload action
        $this->postJson('/api/sync/upload', [
            'actions' => [
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000002',
                    'action_type' => 'create_post',
                    'payload' => ['topic_id' => $topic->id, 'content' => 'Offline post'],
                ],
            ],
        ], $this->apiHeaders($token));

        // Sync
        $response = $this->postJson('/api/sync', [
            'device_id' => 'browser-test-002',
        ], $this->apiHeaders($token));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('posts', ['Post_Content' => 'Offline post', 'Created_By' => $user->id]);
    }

    public function test_pending_actions_endpoint_returns_list(): void
    {
        $user = User::factory()->create();
        $token = $this->actingAsApi($user);

        $this->getJson('/api/sync/pending', $this->apiHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['pending_actions']);
    }

    public function test_status_endpoint_returns_sync_info(): void
    {
        $user = User::factory()->create();
        $token = $this->actingAsApi($user);

        $this->getJson('/api/sync/status', $this->apiHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['online', 'pending_actions']);
    }

    public function test_cannot_sync_as_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = $this->actingAsApi($userA);
        $tokenB = $this->actingAsApi($userB);

        // Register device for userA and upload an action
        $this->postJson('/api/sync/device', [
            'device_id' => 'browser-a',
            'device_name' => 'Browser A',
        ], $this->apiHeaders($tokenA));

        $this->postJson('/api/sync/upload', [
            'actions' => [
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000003',
                    'action_type' => 'create_post',
                    'payload' => ['topic_id' => 999, 'content' => 'secret'],
                ],
            ],
        ], $this->apiHeaders($tokenA));

        // userB syncs with their own (unregistered) device — gets 404
        $this->postJson('/api/sync', [
            'device_id' => 'browser-b',
        ], $this->apiHeaders($tokenB))
            ->assertNotFound();
    }

    public function test_sync_resolves_client_topic_id_for_offline_post(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = $this->actingAsApi($user);

        $group = Group::factory()->create();
        GroupMember::create([
            'Group_ID' => $group->id,
            'User_ID' => $user->id,
            'Member_Status' => 'active',
            'Member_Role' => 'member',
        ]);

        $deviceId = 'desktop-test-001';
        $this->postJson('/api/sync/device', [
            'device_id' => $deviceId,
            'device_name' => 'Desktop Test',
            'device_type' => 'desktop',
        ], $this->apiHeaders($token));

        $clientTopicId = 42;
        $this->postJson('/api/sync/upload', [
            'actions' => [
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000010',
                    'action_type' => 'create_topic',
                    'payload' => [
                        'group_id' => $group->id,
                        'title' => 'Offline topic',
                        'description' => 'Created offline',
                        'client_topic_id' => $clientTopicId,
                    ],
                ],
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000011',
                    'action_type' => 'create_post',
                    'payload' => [
                        'topic_id' => $clientTopicId,
                        'content' => 'Offline reply',
                    ],
                ],
            ],
        ], $this->apiHeaders($token));

        $response = $this->postJson('/api/sync', [
            'device_id' => $deviceId,
        ], $this->apiHeaders($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced_records', 2);

        $topic = Topic::where('Title', 'Offline topic')->first();
        $this->assertNotNull($topic);
        $this->assertDatabaseHas('posts', [
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Offline reply',
            'Created_By' => $user->id,
        ]);
    }

    public function test_sync_applies_excluded_users_on_create_post(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $otherMember = User::factory()->create(['role' => 'student']);
        $token = $this->actingAsApi($user);

        $group = Group::factory()->create();
        GroupMember::create(['Group_ID' => $group->id, 'User_ID' => $user->id, 'Member_Status' => 'active', 'Member_Role' => 'member']);
        GroupMember::create(['Group_ID' => $group->id, 'User_ID' => $otherMember->id, 'Member_Status' => 'active', 'Member_Role' => 'member']);
        $topic = Topic::factory()->create(['Group_ID' => $group->id, 'Created_By' => $user->id]);

        $deviceId = 'browser-exclude-test';
        $this->postJson('/api/sync/device', [
            'device_id' => $deviceId,
            'device_name' => 'Test Browser',
        ], $this->apiHeaders($token));

        $this->postJson('/api/sync/upload', [
            'actions' => [
                [
                    'action_uuid' => '10000000-0000-4000-8000-000000000020',
                    'action_type' => 'create_post',
                    'payload' => [
                        'topic_id' => $topic->id,
                        'content' => 'Hidden from member',
                        'excluded_users' => [$otherMember->id],
                    ],
                ],
            ],
        ], $this->apiHeaders($token));

        $this->postJson('/api/sync', [
            'device_id' => $deviceId,
        ], $this->apiHeaders($token))->assertOk();

        $post = Post::where('Post_Content', 'Hidden from member')->first();
        $this->assertNotNull($post);
        $this->assertTrue($post->hiddenFromUsers()->where('users.id', $otherMember->id)->exists());
    }
}
