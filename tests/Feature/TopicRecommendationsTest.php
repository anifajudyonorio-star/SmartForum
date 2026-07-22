<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Topic;
use App\Models\User;
use App\Services\MachineLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
