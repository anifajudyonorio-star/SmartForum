<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumApiTest extends TestCase
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

    public function test_groups_index_requires_auth(): void
    {
        $this->getJson('/api/groups')->assertUnauthorized();
    }

    public function test_member_can_list_their_groups(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson('/api/groups', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonPath('groups.0.id', $group->id);
    }

    public function test_member_can_view_group_with_stats(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        Topic::factory()->create(['Group_ID' => $group->id, 'Created_By' => $user->id]);

        $this->getJson("/api/groups/{$group->id}", $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonStructure([
                'group',
                'members',
                'topics',
                'stats' => ['members_count', 'topics_count'],
            ]);
    }

    public function test_topics_index_returns_member_topics(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $topic = Topic::factory()->create(['Group_ID' => $group->id, 'Created_By' => $user->id]);

        $this->getJson('/api/topics', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonPath('topics.0.id', $topic->id);
    }

    public function test_dashboard_returns_student_stats(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson('/api/dashboard', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonPath('role', 'student')
            ->assertJsonStructure(['stats' => ['my_posts', 'my_topics', 'groups']]);
    }
}
