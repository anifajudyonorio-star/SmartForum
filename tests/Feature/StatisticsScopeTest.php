<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsScopeTest extends TestCase
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

    public function test_group_admin_statistics_are_scoped_to_administered_groups(): void
    {
        $groupAdmin = User::factory()->create(['role' => 'student']);
        $otherAdmin = User::factory()->create(['role' => 'student']);

        $managedGroup = Group::factory()->create(['Created_By' => $groupAdmin->id, 'Group_Name' => 'OOP']);
        $otherGroup = Group::factory()->create(['Created_By' => $otherAdmin->id, 'Group_Name' => 'Networking']);

        $managedGroup->members()->attach($groupAdmin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $otherGroup->members()->attach($otherAdmin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $response = $this->getJson('/api/statistics', $this->apiHeaders($groupAdmin));

        $response->assertOk()
            ->assertJsonPath('scope', 'group_admin')
            ->assertJsonCount(1, 'group_summaries')
            ->assertJsonPath('group_summaries.0.group_name', 'OOP')
            ->assertJsonPath('total_groups', 1);
    }

    public function test_system_admin_statistics_include_all_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Group::factory()->count(2)->create();

        $response = $this->getJson('/api/statistics', $this->apiHeaders($admin));

        $response->assertOk()
            ->assertJsonPath('scope', 'system')
            ->assertJsonPath('total_groups', 2);
    }

    public function test_group_admin_cannot_view_other_group_statistics(): void
    {
        $groupAdmin = User::factory()->create(['role' => 'student']);
        $otherGroup = Group::factory()->create();

        $ownGroup = Group::factory()->create(['Created_By' => $groupAdmin->id]);
        $ownGroup->members()->attach($groupAdmin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $this->getJson("/api/statistics/groups/{$ownGroup->id}", $this->apiHeaders($groupAdmin))
            ->assertOk();

        $this->getJson("/api/statistics/groups/{$otherGroup->id}", $this->apiHeaders($groupAdmin))
            ->assertForbidden();
    }

    public function test_non_group_admin_cannot_access_statistics(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();

        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $this->getJson('/api/statistics', $this->apiHeaders($student))
            ->assertForbidden();
    }

    public function test_group_admin_participation_is_scoped_to_administered_groups(): void
    {
        $groupAdmin = User::factory()->create(['role' => 'lecturer']);

        $managedGroup = Group::factory()->create(['Group_Name' => 'OOP']);
        $memberGroup = Group::factory()->create(['Group_Name' => 'Other Member Group']);

        $managedGroup->members()->attach($groupAdmin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $memberGroup->members()->attach($groupAdmin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        $response = $this->getJson('/api/participation', $this->apiHeaders($groupAdmin));

        $response->assertOk()
            ->assertJsonCount(1, 'available_groups')
            ->assertJsonPath('available_groups.0.name', 'OOP');
    }
}
