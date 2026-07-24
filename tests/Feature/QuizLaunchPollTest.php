<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizLaunchPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_poll_returns_active_quiz_ready_to_launch(): void
    {
        [$student, $quiz] = $this->studentWithQuiz(now()->subMinute(), now()->addHour());

        $this->actingAs($student)
            ->getJson(route('student.quizzes.launch-poll'))
            ->assertOk()
            ->assertJsonPath('quizzes.0.id', $quiz->id)
            ->assertJsonPath('quizzes.0.status', Quiz::STATUS_ACTIVE)
            ->assertJsonPath('quizzes.0.has_open_attempt', false);
    }

    public function test_student_poll_includes_upcoming_quiz_with_countdown(): void
    {
        [$student, $quiz] = $this->studentWithQuiz(now()->addMinutes(2), now()->addHour());

        $response = $this->actingAs($student)
            ->getJson(route('student.quizzes.launch-poll'))
            ->assertOk();

        $response->assertJsonPath('quizzes.0.id', $quiz->id)
            ->assertJsonPath('quizzes.0.status', Quiz::STATUS_SCHEDULED);

        $this->assertGreaterThan(0, $response->json('quizzes.0.seconds_until_start'));
    }

    public function test_completed_quizzes_are_excluded_from_launch_poll(): void
    {
        [$student, $quiz] = $this->studentWithQuiz(now()->subMinute(), now()->addHour());

        QuizResult::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 1,
            'maximum_score' => 1,
            'participation_marks' => 0,
            'total_score' => 1,
            'maximum_total_score' => 1,
            'graded_at' => now(),
        ]);

        $this->actingAs($student)
            ->getJson(route('student.quizzes.launch-poll'))
            ->assertOk()
            ->assertJsonCount(0, 'quizzes');
    }

    public function test_api_launch_poll_returns_active_quiz_for_desktop_client(): void
    {
        [$student, $quiz] = $this->studentWithQuiz(now()->subMinute(), now()->addHour());

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/student/quizzes/launch-poll')
            ->assertOk()
            ->assertJsonPath('quizzes.0.id', $quiz->id)
            ->assertJsonPath('quizzes.0.status', Quiz::STATUS_ACTIVE);
    }

    /**
     * @return array{0: User, 1: Quiz}
     */
    private function studentWithQuiz($start, $end): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::create([
            'Group_Name' => 'Launch Group '.uniqid(),
            'Description' => 'Launch poll tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Launch Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Launch Quiz '.uniqid(),
            'description' => 'Popup test quiz',
            'duration' => 15,
            'participation_marks' => 0,
            'start_time' => $start,
            'end_time' => $end,
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $lecturer->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Launch question?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 1,
        ]);
        foreach (['Correct', 'Wrong A', 'Wrong B', 'Wrong C'] as $index => $text) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $text,
                'is_correct' => $index === 0,
            ]);
        }

        return [$student, $quiz];
    }
}
