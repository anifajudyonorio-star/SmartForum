<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Topic;
use App\Models\TopicView;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicViewTrackingTest extends TestCase
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

    public function test_api_view_records_one_row_per_user_and_topic(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();
        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $user->id,
        ]);

        CarbonImmutable::setTestNow('2026-07-25 10:00:00');
        $this->postJson("/api/topics/{$topic->id}/view", [], $this->apiHeaders($user))
            ->assertOk()
            ->assertJsonPath('success', true);

        CarbonImmutable::setTestNow('2026-07-25 11:00:00');
        $this->postJson("/api/topics/{$topic->id}/view", [], $this->apiHeaders($user))
            ->assertOk();

        $this->assertDatabaseCount('topic_views', 1);
        $this->assertDatabaseHas('topic_views', [
            'user_id' => $user->id,
            'topic_id' => $topic->id,
        ]);

        $view = TopicView::firstOrFail();
        $this->assertSame('2026-07-25 11:00:00', $view->viewed_at->format('Y-m-d H:i:s'));

        CarbonImmutable::setTestNow();
    }

    public function test_api_view_is_forbidden_for_non_members(): void
    {
        $member = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create();
        $group->members()->attach($member->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $member->id,
        ]);

        $this->postJson("/api/topics/{$topic->id}/view", [], $this->apiHeaders($outsider))
            ->assertForbidden();

        $this->assertDatabaseCount('topic_views', 0);
    }

    public function test_unique_index_prevents_duplicate_view_rows(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $topic = Topic::factory()->create(['Created_By' => $user->id]);

        TopicView::record($user->id, $topic->id);
        TopicView::record($user->id, $topic->id);

        $this->assertDatabaseCount('topic_views', 1);
    }
}
