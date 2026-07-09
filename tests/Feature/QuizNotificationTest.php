<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_notifications_include_the_scheduled_and_end_times(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $category = QuizCategory::create([
            'category_name' => 'Upcoming Category',
            'description' => 'Category for upcoming quiz tests',
            'created_by' => $student->id,
        ]);

        $quiz = Quiz::create([
            'category_id' => $category->id,
            'title' => 'Upcoming Quiz',
            'description' => 'Upcoming description',
            'duration' => 10,
            'participation_marks' => 5,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'Scheduled',
            'created_by' => $student->id,
        ]);

        Notification::create([
            'user_ID' => $student->id,
            'Notification_Type' => 'Quiz',
            'Notification_Title' => 'New Quiz Available',
            'Message' => 'A new quiz is available.',
            'Is_Read' => false,
            'Post_ID' => null,
            'quiz_id' => $quiz->id,
            'expires_at' => $quiz->end_time,
        ]);

        $response = $this->actingAs($student)->get('/notifications');

        $response->assertOk();
        $response->assertSee($quiz->start_time->format('M j, Y g:i A'));
        $response->assertSee($quiz->end_time->format('M j, Y g:i A'));
    }

    public function test_expired_quiz_notifications_are_hidden_from_students(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $category = QuizCategory::create([
            'category_name' => 'Test Category',
            'description' => 'Category for tests',
            'created_by' => $student->id,
        ]);

        $quiz = Quiz::create([
            'category_id' => $category->id,
            'title' => 'Sample Quiz',
            'description' => 'Sample description',
            'duration' => 10,
            'participation_marks' => 5,
            'start_time' => now()->subHour(),
            'end_time' => now()->subMinute(),
            'status' => 'Active',
            'created_by' => $student->id,
        ]);

        Notification::create([
            'user_ID' => $student->id,
            'Notification_Type' => 'Quiz',
            'Notification_Title' => 'New Quiz Available',
            'Message' => 'A new quiz is available.',
            'Is_Read' => false,
            'Post_ID' => null,
            'quiz_id' => $quiz->id,
            'expires_at' => $quiz->end_time,
        ]);

        $response = $this->actingAs($student)->get('/notifications');

        $response->assertOk();
        $response->assertDontSee('New Quiz Available');
    }

    public function test_only_assigned_group_members_receive_quiz_notifications(): void
    {
        $lecturer = User::factory()->create(['role' => 'lecturer']);
        $targetStudent = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $group = Group::create([
            'Group_Name' => 'Class A',
            'Description' => 'Assigned students for quizzes',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);

        $group->members()->attach($targetStudent->id, ['Member_Status' => 'Active']);

        $category = QuizCategory::create([
            'category_name' => 'Group Category',
            'description' => 'Group category for quiz notification test',
            'created_by' => $lecturer->id,
        ]);

        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Group Quiz',
            'description' => 'Group quiz description',
            'duration' => 15,
            'participation_marks' => 5,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => 'Scheduled',
            'created_by' => $lecturer->id,
        ]);

        // Add at least one question so the quiz can be published (publish guard).
        \App\Models\Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Sample question?',
            'question_type' => 'Multiple Choice',
            'marks' => 1,
        ]);

        $response = $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $quiz));

        $response->assertRedirect(route('quizzes.index'));
        $this->assertDatabaseHas('notifications', [
            'user_ID' => $targetStudent->id,
            'quiz_id' => $quiz->id,
            'Notification_Title' => 'New Quiz Available',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_ID' => $otherStudent->id,
            'quiz_id' => $quiz->id,
        ]);
    }
}
