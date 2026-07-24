<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentQuizApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_enroll_and_preview_quizzes_via_api(): void
    {
        [$lecturer, $student, $quiz, $category] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);
        $this->patchJson('/api/quizzes/'.$quiz->id.'/publish')->assertOk();

        Sanctum::actingAs($student);

        $this->getJson('/api/student/quizzes')
            ->assertOk()
            ->assertJsonPath('enrolled_category', null)
            ->assertJsonCount(0, 'quizzes');

        $this->postJson('/api/student/quizzes/enroll', ['category_id' => $category->id])
            ->assertCreated()
            ->assertJsonPath('enrolled_category.name', $category->category_name);

        $this->getJson('/api/student/quizzes')
            ->assertOk()
            ->assertJsonCount(1, 'quizzes')
            ->assertJsonPath('quizzes.0.title', $quiz->title);

        $this->getJson('/api/student/quizzes/'.$quiz->id)
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('can_start', true);
    }

    public function test_student_launch_poll_returns_active_quiz_via_api(): void
    {
        [$lecturer, $student, $quiz, $category] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);
        $this->patchJson('/api/quizzes/'.$quiz->id.'/publish')->assertOk();

        Sanctum::actingAs($student);
        $this->postJson('/api/student/quizzes/enroll', ['category_id' => $category->id])
            ->assertCreated();

        $this->getJson('/api/student/quizzes/launch-poll')
            ->assertOk()
            ->assertJsonPath('quizzes.0.id', $quiz->id)
            ->assertJsonPath('quizzes.0.status', Quiz::STATUS_ACTIVE)
            ->assertJsonPath('quizzes.0.has_open_attempt', false);
    }

    public function test_student_progress_endpoint_returns_results_via_api(): void
    {
        [$lecturer, $student, $quiz, $category] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);
        $this->patchJson('/api/quizzes/'.$quiz->id.'/publish')->assertOk();

        Sanctum::actingAs($student);
        $this->postJson('/api/student/quizzes/enroll', ['category_id' => $category->id])
            ->assertCreated();

        $this->getJson('/api/student/quizzes/progress')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'results');
    }

    public function test_lecturer_can_list_publish_and_delete_quizzes_via_api(): void
    {
        [$lecturer, , $quiz] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/quizzes')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('quizzes.0.can_publish', true)
            ->assertJsonStructure(['categories', 'groups']);

        $this->patchJson('/api/quizzes/'.$quiz->id.'/publish')
            ->assertOk()
            ->assertJsonPath('quiz.is_published', true);

        $draft = Quiz::create([
            'category_id' => $quiz->category_id,
            'group_id' => $quiz->group_id,
            'title' => 'Draft Quiz',
            'description' => 'Draft only',
            'duration' => 30,
            'participation_marks' => 0,
            'start_time' => now()->addHour(),
            'end_time' => now()->addDays(2),
            'status' => Quiz::STATUS_DRAFT,
            'created_by' => $lecturer->id,
        ]);

        $this->deleteJson('/api/quizzes/'.$draft->id)
            ->assertOk();

        $this->assertDatabaseMissing('quizzes', ['id' => $draft->id]);
    }

    public function test_lecturer_can_create_quiz_via_api(): void
    {
        [$lecturer, , $quiz, $category] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);

        $this->postJson('/api/quizzes', [
            'category_id' => $category->id,
            'group_id' => $quiz->group_id,
            'title' => 'Created Via API',
            'description' => 'Desktop create flow',
            'duration' => 20,
            'participation_marks' => 2,
            'start_time' => now()->addHour()->format('Y-m-d\TH:i'),
            'end_time' => now()->addHours(2)->format('Y-m-d\TH:i'),
        ])
            ->assertCreated()
            ->assertJsonPath('quiz.title', 'Created Via API')
            ->assertJsonPath('quiz.lifecycle_status', Quiz::STATUS_DRAFT);

        $this->assertDatabaseHas('quizzes', [
            'title' => 'Created Via API',
            'created_by' => $lecturer->id,
            'status' => Quiz::STATUS_DRAFT,
        ]);
    }

    public function test_lecturer_can_review_quiz_with_questions_via_api(): void
    {
        [$lecturer, , $quiz] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/quizzes/'.$quiz->id)
            ->assertOk()
            ->assertJsonPath('quiz.id', $quiz->id)
            ->assertJsonPath('quiz.title', $quiz->title)
            ->assertJsonPath('questions.0.correct_answer', 'A')
            ->assertJsonStructure([
                'quiz' => ['id', 'title', 'questions_count'],
                'questions' => [['id', 'question', 'options', 'options_display', 'correct_answer']],
                'can_edit_questions',
            ]);
    }

    public function test_lecturer_can_create_question_via_api(): void
    {
        [$lecturer, , $quiz] = $this->studentQuizFixture();
        Sanctum::actingAs($lecturer);

        $this->postJson('/api/questions', [
            'quiz_id' => $quiz->id,
            'question' => 'What is 2 + 2?',
            'question_type' => 'Multiple Choice',
            'marks' => 5,
            'options' => ['4', '3', '5', '6'],
            'correct_option' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('question.quiz_id', $quiz->id)
            ->assertJsonPath('question.correct_answer', 'A');

        $this->getJson('/api/questions')
            ->assertOk()
            ->assertJsonFragment(['question' => 'What is 2 + 2?']);
    }

    /**
     * @return array{0: User, 1: User, 2: Quiz, 3: QuizCategory}
     */
    private function studentQuizFixture(): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::create([
            'Group_Name' => 'Quiz API Group '.uniqid(),
            'Description' => 'Quiz API tests',
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
            'category_name' => 'Quiz API Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'API Quiz '.uniqid(),
            'description' => 'Available through the API',
            'duration' => 30,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addDay(),
            'status' => Quiz::STATUS_DRAFT,
            'created_by' => $lecturer->id,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => '2 + 2 = ?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 5,
        ]);

        foreach (['4', '3', '5', '6'] as $index => $text) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $text,
                'is_correct' => $index === 0,
            ]);
        }

        return [$lecturer, $student, $quiz->fresh(), $category];
    }
}
