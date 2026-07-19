<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupJoinRequestTest extends TestCase
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

    public function test_web_join_request_redirects_with_success(): void
    {
        $requester = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Group_Name' => 'Open Group']);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $this->actingAs($requester)
            ->post(route('groups.join', $group))
            ->assertRedirect(route('groups.explore'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('group_members', [
            'User_ID' => $requester->id,
            'Group_ID' => $group->id,
            'Member_Status' => GroupMember::STATUS_PENDING,
        ]);
    }

    public function test_explore_lists_groups_user_is_not_a_member_of(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $memberGroup = Group::factory()->create(['Group_Name' => 'Joined Group']);
        $exploreGroup = Group::factory()->create(['Group_Name' => 'Open Group']);

        $memberGroup->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson('/api/groups/explore', $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.name', 'Open Group')
            ->assertJsonPath('groups.0.join_status', 'none');
    }

    public function test_user_can_request_to_join_and_admin_is_notified(): void
    {
        $requester = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Group_Name' => 'Algorithms']);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $this->postJson("/api/groups/{$group->id}/join", [], $this->apiHeaders($requester))
            ->assertCreated();

        $this->assertDatabaseHas('group_members', [
            'User_ID' => $requester->id,
            'Group_ID' => $group->id,
            'Member_Status' => GroupMember::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_ID' => $admin->id,
            'Notification_Type' => 'GroupJoinRequest',
            'group_id' => $group->id,
        ]);

        $this->getJson('/api/groups/explore', $this->apiHeaders($requester))
            ->assertOk()
            ->assertJsonPath('groups.0.join_status', 'pending');
    }

    public function test_group_admin_can_approve_join_request(): void
    {
        $requester = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $group->members()->attach($requester->id, [
            'Member_Status' => GroupMember::STATUS_PENDING,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->postJson(
            "/api/groups/{$group->id}/join-requests/{$requester->id}/approve",
            [],
            $this->apiHeaders($admin)
        )->assertOk();

        $this->assertDatabaseHas('group_members', [
            'User_ID' => $requester->id,
            'Group_ID' => $group->id,
            'Member_Status' => GroupMember::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_ID' => $requester->id,
            'Notification_Type' => 'GroupJoinApproved',
            'group_id' => $group->id,
        ]);

        $this->getJson("/api/groups/{$group->id}", $this->apiHeaders($requester))
            ->assertOk();
    }

    public function test_pending_member_cannot_view_group_content(): void
    {
        $requester = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($requester->id, [
            'Member_Status' => GroupMember::STATUS_PENDING,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson("/api/groups/{$group->id}", $this->apiHeaders($requester))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_approve_join_requests(): void
    {
        $requester = User::factory()->create(['role' => 'student']);
        $member = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($member->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $group->members()->attach($requester->id, [
            'Member_Status' => GroupMember::STATUS_PENDING,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->postJson(
            "/api/groups/{$group->id}/join-requests/{$requester->id}/approve",
            [],
            $this->apiHeaders($member)
        )->assertForbidden();
    }
}
