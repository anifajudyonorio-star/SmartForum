<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\InactiveMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveMemberMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_resets_inactivity_warnings(): void
    {
        [$group, $student, $topic] = $this->groupWithStudent();

        $group->members()->updateExistingPivot($student->id, [
            'warnings' => 1,
            'inactive_warning_sent_at' => now()->subDay(),
            'last_activity_at' => now()->subDays(20),
        ]);

        $this->actingAs($student)->post(route('posts.store', $topic), [
            'Post_Content' => 'I am back',
        ])->assertRedirect();

        $membership = $group->fresh()->membership($student->id);

        $this->assertSame(0, (int) $membership->warnings);
        $this->assertNull($membership->inactive_warning_sent_at);
        $this->assertNotNull($membership->last_activity_at);
    }

    public function test_inactivity_job_issues_first_warning(): void
    {
        [$group, $student] = $this->groupWithStudent();

        $group->update([
            'inactivity_monitoring_enabled' => true,
            'inactivity_threshold_days' => 7,
            'inactivity_grace_days' => 3,
            'inactivity_blacklist_days' => 14,
        ]);

        $group->members()->updateExistingPivot($student->id, [
            'last_activity_at' => now()->subDays(10),
            'warnings' => 0,
        ]);

        $result = app(InactiveMemberService::class)->processAll();

        $this->assertSame(1, $result['warned']);
        $this->assertSame(1, (int) $group->fresh()->membership($student->id)->warnings);
        $this->assertDatabaseHas('notifications', [
            'user_ID' => $student->id,
            'Notification_Type' => 'warning',
        ]);
    }

    public function test_second_warning_then_timed_suspension(): void
    {
        [$group, $student] = $this->groupWithStudent();

        $group->update([
            'inactivity_monitoring_enabled' => true,
            'inactivity_threshold_days' => 7,
            'inactivity_grace_days' => 1,
            'inactivity_blacklist_days' => 10,
        ]);

        $group->members()->updateExistingPivot($student->id, [
            'last_activity_at' => now()->subDays(20),
            'warnings' => 2,
            'inactive_warning_sent_at' => now()->subDays(2),
        ]);

        $result = app(InactiveMemberService::class)->processAll();

        $this->assertSame(1, $result['suspended']);

        $membership = $group->fresh()->membership($student->id);
        $this->assertSame(GroupMember::STATUS_SUSPENDED, $membership->Member_Status);
        $this->assertNotNull($membership->suspended_until);
        $this->assertTrue($membership->suspended_until->isFuture());
    }

    public function test_inactivity_job_issues_second_warning_before_suspension(): void
    {
        [$group, $student] = $this->groupWithStudent();

        $group->update([
            'inactivity_monitoring_enabled' => true,
            'inactivity_threshold_days' => 7,
            'inactivity_grace_days' => 1,
            'inactivity_blacklist_days' => 10,
        ]);

        $group->members()->updateExistingPivot($student->id, [
            'last_activity_at' => now()->subDays(20),
            'warnings' => 1,
            'inactive_warning_sent_at' => now()->subDays(2),
        ]);

        $result = app(InactiveMemberService::class)->processAll();

        $this->assertSame(1, $result['warned']);
        $this->assertSame(2, (int) $group->fresh()->membership($student->id)->warnings);
    }

    public function test_expired_suspension_is_released(): void
    {
        [$group, $student] = $this->groupWithStudent();

        $group->members()->updateExistingPivot($student->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
            'warnings' => 2,
            'suspended_until' => now()->subDay(),
        ]);

        $released = app(InactiveMemberService::class)->releaseExpiredSuspensions();

        $this->assertSame(1, $released);
        $membership = $group->fresh()->membership($student->id);
        $this->assertSame(GroupMember::STATUS_ACTIVE, $membership->Member_Status);
        $this->assertNull($membership->suspended_until);
    }

    public function test_inactivity_command_runs_successfully(): void
    {
        $this->artisan('moderation:process-inactivity')
            ->assertSuccessful();
    }

    /**
     * @return array{0: Group, 1: User, 2: Topic}
     */
    private function groupWithStudent(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $group = Group::create([
            'Group_Name' => 'Inactivity Group',
            'Description' => 'Testing inactivity monitoring',
            'Created_By' => $admin->id,
            'Status' => 'Active',
            'inactivity_monitoring_enabled' => true,
            'inactivity_threshold_days' => 7,
            'inactivity_grace_days' => 2,
            'inactivity_blacklist_days' => 5,
        ]);

        $group->members()->attach($admin->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
            'warnings' => 0,
            'last_activity_at' => now()->subDays(30),
        ]);

        $topic = Topic::create([
            'Group_ID' => $group->id,
            'Title' => 'General',
            'Topic_Description' => 'General discussion',
            'Created_By' => $admin->id,
        ]);

        Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Seed post',
            'Created_By' => $admin->id,
        ]);

        return [$group, $student, $topic];
    }
}
