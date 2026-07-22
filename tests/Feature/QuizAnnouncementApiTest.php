<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_can_fetch_post_and_delete_announcements_via_api(): void
    {
        [$lecturer, $category] = $this->lecturerCategory();
        Sanctum::actingAs($lecturer);

        $this->postJson('/api/quiz-announcements', [
            'category_id' => $category->id,
            'title' => 'Midterm Reminder',
            'message' => 'Bring your calculator tomorrow.',
        ])
            ->assertCreated()
            ->assertJsonPath('announcement.title', 'Midterm Reminder');

        $announcement = QuizAnnouncement::firstOrFail();

        $this->getJson('/api/quiz-announcements')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('announcements.0.title', 'Midterm Reminder')
            ->assertJsonPath('announcements.0.can_delete', true);

        $this->deleteJson('/api/quiz-announcements/'.$announcement->id)
            ->assertOk();

        $this->assertDatabaseMissing('quiz_announcements', ['id' => $announcement->id]);
    }

    public function test_student_feed_returns_only_enrolled_category_announcements(): void
    {
        [$lecturer, $category] = $this->lecturerCategory();
        $otherCategory = QuizCategory::create([
            'category_name' => 'Other Title',
            'created_by' => $lecturer->id,
        ]);
        $student = User::factory()->create(['role' => 'student']);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);

        QuizAnnouncement::create([
            'category_id' => $category->id,
            'created_by' => $lecturer->id,
            'title' => 'Visible Note',
            'message' => 'For enrolled students only',
        ]);
        QuizAnnouncement::create([
            'category_id' => $otherCategory->id,
            'created_by' => $lecturer->id,
            'title' => 'Hidden Note',
            'message' => 'Other category',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/announcements')
            ->assertOk()
            ->assertJsonPath('enrolled_category.name', $category->category_name)
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Visible Note');
    }

    public function test_unenrolled_student_feed_is_empty(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/student/announcements')
            ->assertOk()
            ->assertJsonPath('enrolled_category', null)
            ->assertJsonCount(0, 'announcements');
    }

    /**
     * @return array{0: User, 1: QuizCategory}
     */
    private function lecturerCategory(): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $group = Group::create([
            'Group_Name' => 'Announcement API Group '.uniqid(),
            'Description' => 'Announcement API tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Announcement API Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        return [$lecturer, $category];
    }
}
