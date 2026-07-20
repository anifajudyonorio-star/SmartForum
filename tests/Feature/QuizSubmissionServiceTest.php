<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use App\Services\QuizSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_submission_persists_answers_and_grading_denominators(): void
    {
        [$student, $quiz, $question, $correct] = $this->quizFixture();
        $attempt = $this->startAttempt($student, $quiz);

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertOk();

        $result = QuizResult::firstOrFail();
        $answer = QuizAttemptAnswer::firstOrFail();

        $this->assertSame(3, $result->score);
        $this->assertSame(3, $result->maximum_score);
        $this->assertSame(5, $result->total_score);
        $this->assertSame(5, $result->maximum_total_score);
        $this->assertSame($attempt->id, $result->attempt_id);
        $this->assertTrue($answer->is_correct);
        $this->assertSame(3, $answer->awarded_marks);
        $this->assertSame(QuizAttempt::STATUS_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_submission_without_an_owned_attempt_is_rejected(): void
    {
        [$student, $quiz, $question, $correct] = $this->quizFixture();

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => 999999,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertSessionHasErrors('attempt_id');

        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_inactive_quiz_cannot_create_an_attempt(): void
    {
        [$student, $quiz] = $this->quizFixture();
        $quiz->update(['status' => Quiz::STATUS_DRAFT]);

        $this->actingAs($student)
            ->get(route('student.quiz.show', ['quiz' => $quiz, 'start' => 1]))
            ->assertSessionHasErrors('quiz');

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    public function test_option_from_another_question_is_rejected(): void
    {
        [$student, $quiz, $question] = $this->quizFixture();
        [, $forgedOption] = $this->createQuestion($quiz, 'Other question');
        $attempt = $this->startAttempt($student, $quiz);

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $forgedOption->id],
            ])
            ->assertSessionHasErrors("answers.{$question->id}");

        $this->assertDatabaseCount('quiz_results', 0);
        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $attempt->fresh()->status);
    }

    public function test_submission_one_second_before_personal_deadline_is_accepted(): void
    {
        $startedAt = CarbonImmutable::parse('2026-07-17 10:00:00');
        $this->travelTo($startedAt);
        [$student, $quiz, $question, $correct] = $this->quizFixture(
            duration: 10,
            endTime: $startedAt->addHour(),
        );
        $attempt = $this->startAttempt($student, $quiz);

        $this->travelTo($startedAt->addMinutes(10)->subSecond());

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertOk();

        $this->assertSame(3, QuizResult::firstOrFail()->score);
        $this->assertSame(QuizAttempt::STATUS_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_exact_personal_deadline_is_expired_and_late_answers_are_ignored(): void
    {
        $startedAt = CarbonImmutable::parse('2026-07-17 10:00:00');
        $this->travelTo($startedAt);
        [$student, $quiz, $question, $correct] = $this->quizFixture(
            duration: 10,
            endTime: $startedAt->addHour(),
        );
        $attempt = $this->startAttempt($student, $quiz);

        $this->travelTo($startedAt->addMinutes(10));

        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertOk();

        $result = QuizResult::firstOrFail();
        $this->assertSame(0, $result->score);
        $this->assertSame(0, $result->total_score);
        $this->assertTrue($result->grading_snapshot['auto_submitted']);
        $this->assertSame(QuizAttempt::STATUS_AUTO_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_global_end_is_the_deadline_for_a_late_joiner(): void
    {
        $startedAt = CarbonImmutable::parse('2026-07-17 10:00:00');
        $globalEnd = $startedAt->addMinutes(5);
        $this->travelTo($startedAt);
        [$student, $quiz, $question, $correct] = $this->quizFixture(
            duration: 10,
            endTime: $globalEnd,
        );
        $attempt = $this->startAttempt($student, $quiz);
        $service = app(QuizSubmissionService::class);

        $this->assertTrue($service->authoritativeDeadline($attempt, $quiz)->equalTo($globalEnd));

        $this->travelTo($globalEnd);
        $service->submit($student, $quiz, $attempt->id, [$question->id => $correct->id]);

        $this->assertSame(0, QuizResult::firstOrFail()->score);
        $this->assertSame(QuizAttempt::STATUS_AUTO_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_replayed_submission_returns_the_existing_result(): void
    {
        [$student, $quiz, $question, $correct] = $this->quizFixture();
        $attempt = $this->startAttempt($student, $quiz);
        $service = app(QuizSubmissionService::class);
        $payload = [$question->id => $correct->id];

        $first = $service->submit($student, $quiz, $attempt->id, $payload);
        $replayed = $service->submit($student, $quiz, $attempt->id, $payload);

        $this->assertTrue($first->is($replayed));
        $this->assertDatabaseCount('quiz_results', 1);
        $this->assertDatabaseCount('quiz_attempt_answers', 1);
    }

    public function test_expired_attempt_is_finalized_and_can_never_be_restarted(): void
    {
        $startedAt = CarbonImmutable::parse('2026-07-17 10:00:00');
        $this->travelTo($startedAt);
        [$student, $quiz] = $this->quizFixture(
            duration: 1,
            endTime: $startedAt->addHour(),
        );
        $attempt = $this->startAttempt($student, $quiz);

        $this->travelTo($startedAt->addMinutes(2));

        $this->actingAs($student)
            ->get(route('student.quiz.show', ['quiz' => $quiz, 'start' => 1]))
            ->assertRedirect(route('student.quizzes'));

        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertDatabaseCount('quiz_results', 1);
        $this->assertSame(0, QuizResult::firstOrFail()->total_score);
        $this->assertSame(QuizAttempt::STATUS_AUTO_SUBMITTED, $attempt->fresh()->status);

        app(QuizSubmissionService::class)->startAttempt($student, $quiz);
        $this->assertDatabaseCount('quiz_attempts', 1);
    }

    public function test_question_option_and_denominator_snapshots_are_immutable(): void
    {
        [$student, $quiz, $question, $correct, $incorrect] = $this->quizFixture();
        $attempt = $this->startAttempt($student, $quiz);

        $question->update(['question' => 'Edited after start', 'marks' => 99]);
        $correct->update(['option_text' => 'Edited option', 'is_correct' => false]);
        $incorrect->update(['is_correct' => true]);

        $result = app(QuizSubmissionService::class)->submit(
            $student,
            $quiz->fresh(),
            $attempt->id,
            [$question->id => $correct->id],
        );
        $answer = QuizAttemptAnswer::firstOrFail();

        $this->assertSame(3, $result->score);
        $this->assertSame(3, $result->maximum_score);
        $this->assertSame('Test question', $answer->question_text_snapshot);
        $this->assertSame('Correct option', $answer->correct_option_text_snapshot);
        $this->assertSame('Correct option', $answer->selected_option_text_snapshot);
        $this->assertSame(3, $answer->question_marks_snapshot);
    }

    private function startAttempt(User $student, Quiz $quiz): QuizAttempt
    {
        $attempt = app(QuizSubmissionService::class)->startAttempt($student, $quiz);
        $this->assertInstanceOf(QuizAttempt::class, $attempt);

        return $attempt;
    }

    /**
     * @return array{User, Quiz, Question, QuestionOption, QuestionOption}
     */
    private function quizFixture(
        int $duration = 10,
        ?CarbonImmutable $endTime = null
    ): array {
        $student = User::factory()->create(['role' => 'student']);
        $category = QuizCategory::create([
            'category_name' => 'Submission Service '.uniqid(),
            'created_by' => $student->id,
        ]);
        $group = Group::create([
            'Group_Name' => 'Submission Group '.uniqid(),
            'Created_By' => $student->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Transactional Quiz '.uniqid(),
            'description' => 'Transactional grading test',
            'duration' => $duration,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => $endTime ?? now()->addHour(),
            'status' => 'Active',
            'created_by' => $student->id,
        ]);
        [$correct, $incorrect, $question] = $this->createQuestion($quiz, 'Test question');

        return [$student, $quiz, $question, $correct, $incorrect];
    }

    /**
     * @return array{QuestionOption, QuestionOption, Question}
     */
    private function createQuestion(Quiz $quiz, string $text): array
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => $text,
            'question_type' => 'Multiple Choice',
            'marks' => 3,
        ]);
        $correct = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Correct option',
            'is_correct' => true,
        ]);
        $incorrect = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Incorrect option',
            'is_correct' => false,
        ]);
        foreach (['Other option A', 'Other option B'] as $text) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $text,
                'is_correct' => false,
            ]);
        }

        return [$correct, $incorrect, $question];
    }
}
