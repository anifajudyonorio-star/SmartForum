<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostHiddenFromTest extends TestCase
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

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user);
    }

    /** @return array{group: Group, topic: Topic, author: User, excluded: User, other: User} */
    private function hiddenPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'student']);
        $excluded = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $author->id]);
        $this->attachMember($group, $author, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $excluded);
        $this->attachMember($group, $other);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $author->id,
        ]);

        return compact('group', 'topic', 'author', 'excluded', 'other');
    }

    public function test_web_store_syncs_excluded_users_to_pivot(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded] = $this->hiddenPostFixture();

        $this->actingAs($author)
            ->post(route('posts.store', $topic), [
                'Post_Content' => 'Secret message for web',
                'excluded_users' => [$excluded->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $post = Post::where('Post_Content', 'Secret message for web')->first();
        $this->assertNotNull($post);

        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);
    }

    public function test_api_store_returns_hidden_from_user_ids(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded] = $this->hiddenPostFixture();

        $this->actingAsApi($author);

        $response = $this->postJson("/api/topics/{$topic->id}/posts", [
            'Post_Content' => 'Secret message for API',
            'excluded_users' => [$excluded->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('post.hidden_from_user_ids', [$excluded->id]);

        $postId = $response->json('post.id');
        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $postId,
            'user_id' => $excluded->id,
        ]);
    }

    public function test_excluded_member_does_not_see_hidden_post_on_web_topic_or_fragment(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded] = $this->hiddenPostFixture();

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Only visible to some members',
            'Created_By' => $author->id,
        ]);
        $post->hiddenFromUsers()->sync([$excluded->id]);

        $this->actingAs($excluded)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertDontSee('Only visible to some members');

        $this->actingAs($excluded)
            ->get(route('topics.posts-fragment', $topic))
            ->assertOk()
            ->assertDontSee('Only visible to some members');
    }

    public function test_author_and_non_excluded_members_see_hidden_post_on_web(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded, 'other' => $other] = $this->hiddenPostFixture();

        Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Selective visibility message',
            'Created_By' => $author->id,
        ])->hiddenFromUsers()->sync([$excluded->id]);

        $this->actingAs($author)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertSee('Selective visibility message');

        $this->actingAs($other)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertSee('Selective visibility message');
    }

    public function test_api_topic_show_filters_posts_hidden_from_current_user(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded, 'other' => $other] = $this->hiddenPostFixture();

        $hiddenPost = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Hidden via API listing',
            'Created_By' => $author->id,
        ]);
        $hiddenPost->hiddenFromUsers()->sync([$excluded->id]);

        Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Public group message',
            'Created_By' => $other->id,
        ]);

        $this->actingAsApi($excluded);

        $this->getJson("/api/topics/{$topic->id}")
            ->assertOk()
            ->assertJsonMissing(['post_content' => 'Hidden via API listing'])
            ->assertJsonFragment(['post_content' => 'Public group message']);

        $this->actingAsApi($author);

        $this->getJson("/api/topics/{$topic->id}")
            ->assertOk()
            ->assertJsonFragment(['post_content' => 'Hidden via API listing'])
            ->assertJsonFragment(['post_content' => 'Public group message']);
    }

    public function test_web_update_can_add_and_remove_exclusions(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded, 'other' => $other] = $this->hiddenPostFixture();

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'Editable hidden post',
            'Created_By' => $author->id,
        ]);

        $this->actingAs($author)
            ->put(route('posts.update', $post), [
                'Post_Content' => 'Editable hidden post',
                'excluded_users' => [$excluded->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);

        $this->actingAs($excluded)
            ->get(route('topics.show', $topic))
            ->assertDontSee('Editable hidden post');

        $this->actingAs($author)
            ->put(route('posts.update', $post), [
                'Post_Content' => 'Editable hidden post',
                'excluded_users' => [$other->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $this->assertDatabaseMissing('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);
        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($excluded)
            ->get(route('topics.show', $topic))
            ->assertSee('Editable hidden post');

        $this->actingAs($other)
            ->get(route('topics.show', $topic))
            ->assertDontSee('Editable hidden post');
    }

    public function test_api_update_syncs_excluded_users(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded] = $this->hiddenPostFixture();

        $post = Post::create([
            'Topic_ID' => $topic->id,
            'Post_Content' => 'API editable hidden post',
            'Created_By' => $author->id,
        ]);

        $this->actingAsApi($author);

        $this->putJson("/api/posts/{$post->id}", [
            'Post_Content' => 'API editable hidden post',
            'excluded_users' => [$excluded->id],
        ])
            ->assertOk()
            ->assertJsonPath('post.hidden_from_user_ids', [$excluded->id]);

        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);

        $this->putJson("/api/posts/{$post->id}", [
            'Post_Content' => 'API editable hidden post',
            'excluded_users' => [],
        ])
            ->assertOk()
            ->assertJsonPath('post.hidden_from_user_ids', []);

        $this->assertDatabaseMissing('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);
    }

    public function test_author_cannot_exclude_themselves(): void
    {
        ['topic' => $topic, 'author' => $author, 'excluded' => $excluded] = $this->hiddenPostFixture();

        $this->actingAs($author)
            ->post(route('posts.store', $topic), [
                'Post_Content' => 'Author always sees own post',
                'excluded_users' => [$author->id, $excluded->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $post = Post::where('Post_Content', 'Author always sees own post')->first();
        $this->assertNotNull($post);

        $this->assertDatabaseHas('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $excluded->id,
        ]);
        $this->assertDatabaseMissing('post_hidden_from', [
            'post_id' => $post->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_topic_creator_is_not_notified_when_excluded_from_new_post(): void
    {
        $creator = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $creator->id]);
        $this->attachMember($group, $creator, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $author);
        $this->attachMember($group, $other);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $creator->id,
        ]);

        $this->actingAs($author)
            ->post(route('posts.store', $topic), [
                'Post_Content' => 'Hidden from topic creator',
                'excluded_users' => [$creator->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $post = Post::where('Post_Content', 'Hidden from topic creator')->first();
        $this->assertNotNull($post);

        $this->assertDatabaseMissing('notifications', [
            'user_ID' => $creator->id,
            'Notification_Type' => 'PostCreated',
            'Post_ID' => $post->id,
        ]);
    }

    public function test_topic_creator_is_notified_when_not_excluded_from_new_post(): void
    {
        $creator = User::factory()->create(['role' => 'student']);
        $author = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);

        $group = Group::factory()->create(['Created_By' => $creator->id]);
        $this->attachMember($group, $creator, GroupMember::ROLE_ADMIN);
        $this->attachMember($group, $author);
        $this->attachMember($group, $other);

        $topic = Topic::factory()->create([
            'Group_ID' => $group->id,
            'Created_By' => $creator->id,
        ]);

        $this->actingAs($author)
            ->post(route('posts.store', $topic), [
                'Post_Content' => 'Visible to topic creator',
                'excluded_users' => [$other->id],
            ])
            ->assertRedirect(route('topics.show', $topic));

        $post = Post::where('Post_Content', 'Visible to topic creator')->first();
        $this->assertNotNull($post);

        $this->assertDatabaseHas('notifications', [
            'user_ID' => $creator->id,
            'Notification_Type' => 'PostCreated',
            'Post_ID' => $post->id,
        ]);
    }
}
