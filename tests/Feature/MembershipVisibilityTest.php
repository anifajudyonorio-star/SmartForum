<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipVisibilityTest extends TestCase
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

    public function test_system_admin_only_sees_member_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $memberGroup = Group::factory()->create(['Group_Name' => 'My Group']);
        $otherGroup = Group::factory()->create(['Group_Name' => 'Other Group']);

        $memberGroup->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $response = $this->getJson('/api/groups', $this->apiHeaders($admin));

        $response->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'My Group');
    }

    public function test_non_member_cannot_view_group(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $this->getJson("/api/groups/{$group->id}", $this->apiHeaders($user))
            ->assertForbidden();
    }

    public function test_non_member_cannot_view_topic_or_posts(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();
        $topic = Topic::factory()->create(['Group_ID' => $group->id]);

        Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Secret message',
            'Created_By' => User::factory()->create()->id,
        ]);

        $this->getJson("/api/topics/{$topic->id}", $this->apiHeaders($user))
            ->assertForbidden();
    }

    public function test_topics_index_excludes_non_member_groups(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $memberGroup = Group::factory()->create();
        $otherGroup = Group::factory()->create();

        $memberGroup->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $visibleTopic = Topic::factory()->create([
            'Group_ID' => $memberGroup->id,
            'Created_By' => $user->id,
        ]);

        Topic::factory()->create([
            'Group_ID' => $otherGroup->id,
            'Created_By' => User::factory()->create()->id,
        ]);

        $this->getJson('/api/topics', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'topics')
            ->assertJsonPath('topics.0.id', $visibleTopic->id);
    }

    public function test_blocked_member_cannot_view_group(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_BLOCKED,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson("/api/groups/{$group->id}", $this->apiHeaders($user))
            ->assertForbidden();

        $this->getJson('/api/groups', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonCount(0, 'groups');
    }
}
