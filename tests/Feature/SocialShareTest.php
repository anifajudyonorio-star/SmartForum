<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_chat_page_exposes_share_metadata(): void
    {
        [$topic] = $this->topicWithPost();

        $this->actingAs(User::first())
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertSee('data-topic-url="'.route('topics.show', $topic).'"', false)
            ->assertSee('data-topic-title="'.$topic->Title.'"', false);
    }

    public function test_post_message_includes_share_button(): void
    {
        [$topic, $post] = $this->topicWithPost();

        $this->actingAs(User::first())
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertSee('share-btn', false)
            ->assertSee('msg-'.$post->id, false);
    }

    /**
     * @return array{0: Topic, 1: Post}
     */
    private function topicWithPost(): array
    {
        $user = User::factory()->create(['role' => 'student']);
        $group = Group::create([
            'Group_Name' => 'Share Group',
            'Description' => 'Share test group',
            'Created_By' => $user->id,
            'Status' => 'Active',
        ]);

        $group->members()->attach($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_ADMIN,
            'warnings' => 0,
        ]);

        $topic = Topic::create([
            'Group_ID' => $group->id,
            'Title' => 'Share Topic',
            'Topic_Description' => 'Topic for sharing posts',
            'Created_By' => $user->id,
        ]);

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Share this message',
            'Created_By' => $user->id,
        ]);

        return [$topic, $post];
    }
}
