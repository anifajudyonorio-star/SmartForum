<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupParticipationSetting;
use App\Models\ParticipationGrade;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationGradingTest extends TestCase
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

    public function test_group_admin_can_update_participation_criteria(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Created_By' => $admin->id]);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $this->putJson(
            "/api/groups/{$group->id}/participation/criteria",
            [
                'topic_points' => 10,
                'post_points' => 4,
                'reply_points' => 1,
                'gold_min' => 80,
                'silver_min' => 50,
                'bronze_min' => 25,
                'manual_marks_max' => 15,
            ],
            $this->apiHeaders($admin)
        )->assertOk()
            ->assertJsonPath('criteria.topic_points', 10)
            ->assertJsonPath('criteria.manual_marks_max', 15);

        $this->assertDatabaseHas('group_participation_settings', [
            'group_id' => $group->id,
            'topic_points' => 10,
            'manual_marks_max' => 15,
        ]);
    }

    public function test_participation_scores_use_criteria_and_manual_marks(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Created_By' => $admin->id]);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        GroupParticipationSetting::defaultsFor($group)->update([
            'topic_points' => 10,
            'post_points' => 5,
            'reply_points' => 2,
            'gold_min' => 100,
            'silver_min' => 60,
            'bronze_min' => 30,
            'manual_marks_max' => 20,
        ]);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $student->id,
        ]);

        $parentPost = Post::create([
            'Topic_ID' => $topic->id,
            'Created_By' => $student->id,
            'Post_Content' => 'Top-level post',
        ]);

        Post::create([
            'Topic_ID' => $topic->id,
            'Parent_Post_ID' => $parentPost->id,
            'Created_By' => $student->id,
            'Post_Content' => 'Reply content',
        ]);

        ParticipationGrade::create([
            'group_id' => $group->id,
            'user_id' => $student->id,
            'manual_marks' => 8,
            'graded_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/participation?group={$group->id}", $this->apiHeaders($admin));

        $response->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('criteria.topic_points', 10);

        $participant = collect($response->json('participants'))
            ->firstWhere('user_id', $student->id);

        $this->assertNotNull($participant);
        $this->assertSame(1, $participant['topics_count']);
        $this->assertSame(1, $participant['posts_count']);
        $this->assertSame(1, $participant['replies_count']);
        $this->assertSame(10 + 5 + 2, $participant['auto_score']);
        $this->assertSame(8, $participant['manual_marks']);
        $this->assertSame(10 + 5 + 2 + 8, $participant['score']);
    }

    public function test_group_admin_can_save_manual_marks_for_member(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Created_By' => $admin->id]);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        GroupParticipationSetting::defaultsFor($group);

        $this->patchJson(
            "/api/groups/{$group->id}/participation/grades/{$student->id}",
            [
                'manual_marks' => 12,
                'notes' => 'Strong facilitator in discussions',
            ],
            $this->apiHeaders($admin)
        )->assertOk()
            ->assertJsonPath('grade.manual_marks', 12)
            ->assertJsonPath('grade.notes', 'Strong facilitator in discussions');

        $this->assertDatabaseHas('participation_grades', [
            'group_id' => $group->id,
            'user_id' => $student->id,
            'manual_marks' => 12,
            'graded_by' => $admin->id,
        ]);
    }

    public function test_manual_marks_are_capped_by_group_setting(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Created_By' => $admin->id]);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
        ]);

        GroupParticipationSetting::defaultsFor($group)->update(['manual_marks_max' => 5]);

        $this->patchJson(
            "/api/groups/{$group->id}/participation/grades/{$student->id}",
            ['manual_marks' => 99],
            $this->apiHeaders($admin)
        )->assertOk()
            ->assertJsonPath('grade.manual_marks', 5);
    }
}
