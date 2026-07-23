<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Topic;
use App\Models\TopicView;
use App\Models\User;
use App\Services\MachineLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopicRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_topics_page_can_show_recommendations_for_non_member_groups_with_join_action(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $recommendedGroup = Group::factory()->create(['Group_Name' => 'Recommended Group']);
        $topic = Topic::factory()->create([
            'Title' => 'Similar topic',
            'Topic_Description' => 'A topic about the same subject.',
            'Group_ID' => $recommendedGroup->id,
            'Created_By' => User::factory()->create()->id,
        ]);

        $this->app->instance(MachineLearningService::class, new class($topic->id) extends MachineLearningService {
            private int $topicId;

            public function __construct(int $topicId)
            {
                $this->topicId = $topicId;
            }

            public function getRecommendations($userId): array
            {
                return [['id' => $this->topicId, 'score' => 0.98]];
            }
        });

        $this->actingAs($user)
            ->get(route('topics.index'))
            ->assertOk()
            ->assertSee('Recommended for you')
            ->assertSee('Similar topic')
            ->assertSee('Request to join group');
    }

    public function test_deleted_groups_are_excluded_from_recommendations(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::factory()->create(['Group_Name' => 'Deleted Group']);
        $topic = Topic::factory()->create([
            'Title' => 'Ghost topic',
            'Topic_Description' => 'Should not appear once the parent group is gone.',
            'Group_ID' => $group->id,
            'Created_By' => User::factory()->create()->id,
        ]);

        $group->delete();

        $this->app->instance(MachineLearningService::class, new class($topic->id) extends MachineLearningService {
            private int $topicId;

            public function __construct(int $topicId)
            {
                $this->topicId = $topicId;
            }

            public function getRecommendations($userId): array
            {
                return [['id' => $this->topicId, 'score' => 0.99]];
            }
        });

        $this->actingAs($user)
            ->get(route('topics.index'))
            ->assertOk()
            ->assertDontSee('Ghost topic');
    }

    public function test_cross_group_related_topic_is_sent_to_ml_service(): void
    {
        Http::fake([
            'localhost:5001/recommend' => Http::response([
                'user_id' => 1,
                'recommendations' => [
                    ['id' => 2, 'title' => 'Agile and Waterfall', 'score' => 0.42],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'student']);
        $debateGroup = Group::factory()->create(['Group_Name' => 'Debate Group']);
        $overviewGroup = Group::factory()->create(['Group_Name' => 'Overview Group']);

        $debateGroup->members()->attach($user->id, [
            'Member_Status' => 'active',
            'Member_Role' => 'member',
        ]);
        $overviewGroup->members()->attach($user->id, [
            'Member_Status' => 'active',
            'Member_Role' => 'member',
        ]);

        $debateTopic = Topic::factory()->create([
            'Title' => 'Agile and Waterfall Debate',
            'Topic_Description' => 'Discussion comparing agile and waterfall methodologies',
            'Group_ID' => $debateGroup->id,
            'Created_By' => $user->id,
        ]);

        Topic::factory()->create([
            'Title' => 'Agile and Waterfall',
            'Topic_Description' => 'Overview of agile and waterfall project management',
            'Group_ID' => $overviewGroup->id,
            'Created_By' => User::factory()->create()->id,
        ]);

        TopicView::create([
            'user_id' => $user->id,
            'topic_id' => $debateTopic->id,
            'viewed_at' => now(),
        ]);

        app(MachineLearningService::class)->getRecommendations($user->id);

        Http::assertSent(function ($request) use ($overviewGroup, $debateGroup, $debateTopic) {
            $payload = $request->data();

            return $request->url() === 'http://localhost:5001/recommend'
                && in_array($debateGroup->id, $payload['engaged_group_ids'], true)
                && ! in_array($overviewGroup->id, $payload['engaged_group_ids'], true)
                && in_array($debateTopic->id, $payload['engaged_topic_ids'], true)
                && collect($payload['topics'])->contains(fn ($topic) => (int) $topic['group_id'] === (int) $overviewGroup->id);
        });
    }
}
