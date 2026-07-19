<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use App\Services\QuizSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizResultOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_submission_creates_a_result_owned_by_the_authenticated_user(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $quiz = $this->createQuiz($student);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Which option is correct?',
            'question_type' => 'Multiple Choice',
            'marks' => 3,
        ]);
        $correct = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Correct',
            'is_correct' => true,
        ]);
        foreach (['Wrong A', 'Wrong B', 'Wrong C'] as $text) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $text,
                'is_correct' => false,
            ]);
        }

        $this->actingAs($student)
            ->get(route('student.quiz.show', ['quiz' => $quiz, 'start' => 1]))
            ->assertOk();

        $attempt = QuizAttempt::whereBelongsTo($student)
            ->whereBelongsTo($quiz)
            ->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 3,
            'total_score' => 5,
        ]);
    }

    public function test_result_belongs_to_user_and_inverse_relationships_are_available(): void
    {
        $student = User::factory()->create();
        $quiz = $this->createQuiz($student);
        $result = QuizResult::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 4,
            'participation_marks' => 2,
            'total_score' => 6,
        ]);

        $this->assertTrue($result->user->is($student));
        $this->assertTrue($student->quizResults->first()->is($result));
        $this->assertTrue($quiz->results->first()->is($result));
    }

    public function test_database_prevents_duplicate_results_for_the_same_quiz_and_user(): void
    {
        $student = User::factory()->create();
        $quiz = $this->createQuiz($student);
        $attributes = [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 0,
            'participation_marks' => 2,
            'total_score' => 2,
        ];

        QuizResult::create($attributes);

        $this->expectException(QueryException::class);
        QuizResult::create($attributes);
    }

    public function test_completed_lookup_and_report_render_use_user_ownership(): void
    {
        $student = User::factory()->create([
            'Fname' => 'Result',
            'Lname' => 'Owner',
            'role' => 'student',
        ]);
        $lecturer = User::factory()->create(['role' => 'lecturer']);
        $quiz = $this->createQuiz($lecturer);
        $quiz->group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'A reportable question',
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
        QuizResult::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 1,
            'participation_marks' => 2,
            'total_score' => 3,
        ]);

        $this->actingAs($student)
            ->get(route('student.quizzes'))
            ->assertOk()
            ->assertSee('Completed');

        $this->actingAs($lecturer)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Result Owner')
            ->assertSee($quiz->title);
    }

    public function test_offline_quiz_result_is_owned_by_the_syncing_user(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $quiz = $this->createQuiz($student);
        $this->addValidQuestion($quiz);
        $attempt = app(QuizSubmissionService::class)->startAttempt($student, $quiz);
        $token = $student->createToken('quiz-sync-test')->plainTextToken;
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];

        $this->postJson('/api/sync/device', [
            'device_id' => 'quiz-device',
            'device_name' => 'Quiz Device',
        ], $headers)->assertOk();

        $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => '20000000-0000-4000-8000-000000000001',
                'action_type' => 'submit_quiz',
                'payload' => [
                    'quiz_id' => $quiz->id,
                    'attempt_id' => $attempt->id,
                    'answers' => [],
                ],
            ]],
        ], $headers)->assertOk();

        $this->postJson('/api/sync', [
            'device_id' => 'quiz-device',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('synced_records', 1);

        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
        ]);
    }

    private function createQuiz(User $creator): Quiz
    {
        $category = QuizCategory::create([
            'category_name' => 'Ownership Tests '.uniqid(),
            'created_by' => $creator->id,
        ]);
        $group = Group::create([
            'Group_Name' => 'Ownership Group '.uniqid(),
            'Created_By' => $creator->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($creator->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => $creator->isLecturer()
                ? GroupMember::ROLE_LECTURER
                : GroupMember::ROLE_MEMBER,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Ownership Quiz '.uniqid(),
            'description' => 'Quiz result ownership test',
            'duration' => 10,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
            'status' => 'Active',
            'created_by' => $creator->id,
        ]);
    }

    private function addValidQuestion(Quiz $quiz): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Offline quiz question',
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

        return $question;
    }
}
