<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_can_post_and_delete_announcements_for_owned_categories(): void
    {
        [$lecturer, $category] = $this->lecturerCategory();

        $this->actingAs($lecturer)
            ->post(route('quiz-announcements.store'), [
                'category_id' => $category->id,
                'title' => 'Midterm Reminder',
                'message' => 'Bring your calculator tomorrow.',
            ])
            ->assertRedirect(route('quiz-announcements.index'));

        $this->assertDatabaseHas('quiz_announcements', [
            'category_id' => $category->id,
            'created_by' => $lecturer->id,
            'title' => 'Midterm Reminder',
        ]);

        $announcement = QuizAnnouncement::firstOrFail();

        $this->actingAs($lecturer)
            ->get(route('quiz-announcements.index'))
            ->assertOk()
            ->assertSee('Midterm Reminder')
            ->assertSee('Bring your calculator tomorrow.');

        $this->actingAs($lecturer)
            ->delete(route('quiz-announcements.destroy', $announcement))
            ->assertRedirect(route('quiz-announcements.index'));

        $this->assertDatabaseMissing('quiz_announcements', ['id' => $announcement->id]);
    }

    public function test_lecturer_cannot_post_to_another_lecturers_category(): void
    {
        [, $category] = $this->lecturerCategory();
        $outsider = User::factory()->lecturer()->create();

        $this->actingAs($outsider)
            ->post(route('quiz-announcements.store'), [
                'category_id' => $category->id,
                'title' => 'Unauthorized',
                'message' => 'Should not post',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('quiz_announcements', 0);
    }

    public function test_student_sees_only_announcements_for_enrolled_category(): void
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

        $this->actingAs($student)
            ->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('Visible Note')
            ->assertDontSee('Hidden Note');
    }

    public function test_unenrolled_student_sees_empty_announcement_prompt(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('student.announcements'))
            ->assertOk()
            ->assertSee('You are not enrolled in a quiz title yet.');
    }

    /**
     * @return array{0: User, 1: QuizCategory}
     */
    private function lecturerCategory(): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $group = Group::create([
            'Group_Name' => 'Announcement Group '.uniqid(),
            'Description' => 'Announcement tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Announcement Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        return [$lecturer, $category];
    }
}
