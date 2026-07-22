<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function attachMember(Group $group, User $user, string $role = GroupMember::ROLE_MEMBER): void
    {
        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => $role,
            'warnings' => 0,
        ]);
    }

    public function test_member_can_report_irrelevant_post_and_hide_it_from_chat(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $member = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $admin->id]);
        $this->attachMember($group, $admin, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $member);
        $this->attachMember($group, $author);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $admin->id,
        ]);

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Buy cheap watches here!',
            'Created_By' => $author->id,
        ]);

        $this->actingAs($member)
            ->postJson(route('posts.report', $post), ['reason' => 'Spam'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('reports', [
            'post_id' => $post->id,
            'reporter_id' => $member->id,
            'group_id' => $group->id,
            'status' => Report::STATUS_PENDING,
            'reason' => 'Spam',
        ]);

        $this->actingAs($member)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertDontSee('Buy cheap watches here!');
    }

    public function test_group_admin_can_restore_reported_post(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $member = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $admin->id]);
        $this->attachMember($group, $admin, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $member);
        $this->attachMember($group, $author);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $admin->id,
        ]);

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Maybe this was fine after all.',
            'Created_By' => $author->id,
        ]);

        $this->actingAs($member)->postJson(route('posts.report', $post))->assertOk();

        $report = Report::firstOrFail();

        $this->actingAs($admin)
            ->post(route('groups.post-reports.restore', [$group, $report]))
            ->assertRedirect();

        $post->refresh();
        $report->refresh();

        $this->assertFalse($post->trashed());
        $this->assertSame(Report::STATUS_RESTORED, $report->status);

        $this->actingAs($member)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertSee('Maybe this was fine after all.');
    }

    public function test_group_admin_can_permanently_delete_reported_post(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $member = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $admin->id]);
        $this->attachMember($group, $admin, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $member);
        $this->attachMember($group, $author);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $admin->id,
        ]);

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Remove this permanently.',
            'Created_By' => $author->id,
        ]);

        $this->actingAs($member)->postJson(route('posts.report', $post))->assertOk();
        $report = Report::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('groups.post-reports.destroy', [$group, $report]))
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('moderation_logs', [
            'admin_id' => $admin->id,
            'group_id' => $group->id,
            'action' => 'post_report_removed',
        ]);
    }

    public function test_report_notifies_group_admins(): void
    {
        $admin = User::factory()->create(['role' => 'student']);
        $member = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $admin->id]);
        $this->attachMember($group, $admin, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $member);
        $this->attachMember($group, $author);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $admin->id,
        ]);

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Off-topic noise.',
            'Created_By' => $author->id,
        ]);

        $this->actingAs($member)->postJson(route('posts.report', $post))->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_ID' => $admin->id,
            'Notification_Type' => 'post_report',
            'group_id' => $group->id,
            'Post_ID' => $post->id,
        ]);
    }
}
