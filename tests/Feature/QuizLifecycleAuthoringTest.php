<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizLifecycleAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_quiz_status_is_derived_from_its_schedule(): void
    {
        $now = CarbonImmutable::parse('2026-07-17 10:00:00');
        $this->travelTo($now);
        [$lecturer, $group, $category] = $this->authoringContext();
        $student = $this->studentIn($group, $category);
        $quiz = $this->quiz($lecturer, $group, $category, [
            'start_time' => $now->addHour(),
            'end_time' => $now->addHours(2),
        ]);
        $this->validQuestion($quiz);

        $this->assertSame(Quiz::STATUS_DRAFT, $quiz->lifecycleStatus());

        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $quiz))
            ->assertRedirect();

        $this->assertSame(Quiz::STATUS_SCHEDULED, $quiz->fresh()->status);
        $this->assertSame(Quiz::STATUS_SCHEDULED, $quiz->fresh()->lifecycleStatus());
        $this->actingAs($student)
            ->get(route('student.quiz.show', $quiz))
            ->assertRedirect(route('student.quizzes'));

        $this->travelTo($now->addHour());
        $this->assertSame(Quiz::STATUS_ACTIVE, $quiz->fresh()->lifecycleStatus());
        $this->assertSame(Quiz::STATUS_SCHEDULED, $quiz->fresh()->status);
        $this->actingAs($student)->get(route('student.quiz.show', $quiz))->assertOk();

        $this->travelTo($now->addHours(2));
        $this->assertSame(Quiz::STATUS_CLOSED, $quiz->fresh()->lifecycleStatus());
    }

    public function test_draft_and_closed_quizzes_are_not_student_accessible(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $student = $this->studentIn($group, $category);
        $draft = $this->quiz($lecturer, $group, $category);
        $closed = $this->quiz($lecturer, $group, $category, [
            'status' => Quiz::STATUS_ACTIVE,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ]);
        $this->validQuestion($draft);
        $this->validQuestion($closed);

        $response = $this->actingAs($student)->get(route('student.quizzes'));
        $response->assertDontSee($draft->title)->assertDontSee($closed->title);
        $this->actingAs($student)
            ->get(route('student.quiz.show', $draft))
            ->assertRedirect(route('student.quizzes'))
            ->assertSessionHasErrors('quiz');
        $this->actingAs($student)
            ->get(route('student.quiz.show', $closed))
            ->assertRedirect(route('student.quizzes'))
            ->assertSessionHasErrors('quiz');
    }

    public function test_publish_rejects_invalid_schedule_and_legacy_short_answer(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $invalidSchedule = $this->quiz($lecturer, $group, $category, [
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHour(),
        ]);
        $this->validQuestion($invalidSchedule);

        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $invalidSchedule))
            ->assertSessionHasErrors('schedule');
        $this->assertSame(Quiz::STATUS_DRAFT, $invalidSchedule->fresh()->status);

        $legacy = $this->quiz($lecturer, $group, $category);
        Question::create([
            'quiz_id' => $legacy->id,
            'question' => 'Legacy manual question',
            'question_type' => 'Short Answer',
            'marks' => 2,
        ]);

        $legacyQuestion = $legacy->questions()->firstOrFail();
        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $legacy))
            ->assertSessionHasErrors("question_{$legacyQuestion->id}");
        $this->assertStringContainsString(
            'Short Answer',
            session('errors')->first("question_{$legacyQuestion->id}"),
        );
        $this->assertSame(Quiz::STATUS_DRAFT, $legacy->fresh()->status);
    }

    public function test_publish_requires_assignment_duration_and_a_fully_valid_question(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category, [
            'group_id' => null,
            'duration' => 0,
        ]);

        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $quiz))
            ->assertSessionHasErrors(['group_id', 'duration', 'questions']);

        $invalidQuestionQuiz = $this->quiz($lecturer, $group, $category);
        $question = $this->validQuestion($invalidQuestionQuiz);
        $question->options()->where('is_correct', false)->firstOrFail()->update(['is_correct' => true]);

        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $invalidQuestionQuiz))
            ->assertSessionHasErrors("question_{$question->id}");
    }

    public function test_question_authoring_rejects_blank_malformed_and_short_answer_payloads(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category);

        $this->actingAs($lecturer)
            ->post(route('questions.store'), $this->questionPayload($quiz, [
                'question' => '   ',
                'options' => ['Correct', 'Wrong', ' ', 'Other'],
            ]))
            ->assertSessionHasErrors(['question', 'options']);
        $this->assertDatabaseCount('questions', 0);

        $this->actingAs($lecturer)
            ->post(route('questions.store'), $this->questionPayload($quiz, [
                'options' => ['Correct', 'Wrong', 'Other'],
                'correct_option' => 9,
            ]))
            ->assertSessionHasErrors(['options', 'correct_option']);
        $this->assertDatabaseCount('questions', 0);

        $this->actingAs($lecturer)
            ->post(route('questions.store'), $this->questionPayload($quiz, [
                'question_type' => 'Short Answer',
                'options' => [],
            ]))
            ->assertSessionHasErrors('question_type');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_true_false_requires_exact_true_and_false_options(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category);

        $this->actingAs($lecturer)
            ->post(route('questions.store'), $this->questionPayload($quiz, [
                'question_type' => Question::TYPE_TRUE_FALSE,
                'options' => ['Yes', 'No'],
                'correct_option' => 0,
            ]))
            ->assertSessionHasErrors('options');

        $this->actingAs($lecturer)
            ->post(route('questions.store'), $this->questionPayload($quiz, [
                'question_type' => Question::TYPE_TRUE_FALSE,
                'options' => [' True ', ' False '],
                'correct_option' => 0,
            ]))
            ->assertRedirect(route('questions.index'));

        $question = Question::firstOrFail();
        $this->assertSame(['True', 'False'], $question->options()->pluck('option_text')->all());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_malformed_update_leaves_question_and_options_unchanged(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category);
        $question = $this->validQuestion($quiz);
        $originalOptions = $question->options()->pluck('option_text')->all();

        $this->actingAs($lecturer)
            ->put(route('questions.update', $question), $this->questionPayload($quiz, [
                'question' => 'Should not persist',
                'options' => ['Only', 'Three', 'Options'],
            ]))
            ->assertSessionHasErrors('options');

        $this->assertSame('Valid question?', $question->fresh()->question);
        $this->assertSame($originalOptions, $question->options()->pluck('option_text')->all());
    }

    public function test_published_assessment_content_is_immutable_but_metadata_can_change(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category);
        $question = $this->validQuestion($quiz);
        $this->actingAs($lecturer)->patch(route('quizzes.publish', $quiz))->assertRedirect();

        $this->actingAs($lecturer)
            ->put(route('questions.update', $question), $this->questionPayload($quiz))
            ->assertSessionHasErrors('question');
        $this->actingAs($lecturer)
            ->delete(route('questions.destroy', $question))
            ->assertSessionHasErrors('question');
        $this->assertDatabaseHas('questions', ['id' => $question->id]);

        $this->actingAs($lecturer)
            ->put(route('quizzes.update', $quiz), [
                'title' => 'Safe metadata title',
                'description' => 'Safe metadata description',
            ])
            ->assertRedirect(route('quizzes.index'));
        $this->assertSame('Safe metadata title', $quiz->fresh()->title);

        $this->actingAs($lecturer)
            ->put(route('quizzes.update', $quiz), [
                'title' => 'Unsafe',
                'description' => 'Unsafe',
                'duration' => 99,
            ])
            ->assertSessionHasErrors('quiz');
        $this->assertSame(15, $quiz->fresh()->duration);
    }

    public function test_published_quiz_and_dependent_category_deletion_are_guarded(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category);
        $this->validQuestion($quiz);
        $this->actingAs($lecturer)->patch(route('quizzes.publish', $quiz))->assertRedirect();

        $this->actingAs($lecturer)
            ->delete(route('quizzes.destroy', $quiz))
            ->assertSessionHasErrors('quiz');
        $this->actingAs($lecturer)
            ->delete(route('quiz-categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseHas('quiz_categories', ['id' => $category->id]);
    }

    public function test_draft_with_attempt_history_cannot_be_edited_or_deleted(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $student = $this->studentIn($group);
        $quiz = $this->quiz($lecturer, $group, $category);
        $question = $this->validQuestion($quiz);
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'started_at' => now(),
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($lecturer)
            ->delete(route('questions.destroy', $question))
            ->assertSessionHasErrors('question');
        $this->actingAs($lecturer)
            ->delete(route('quizzes.destroy', $quiz))
            ->assertSessionHasErrors('quiz');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_review_displays_authored_and_participation_denominator(): void
    {
        [$lecturer, $group, $category] = $this->authoringContext();
        $quiz = $this->quiz($lecturer, $group, $category, ['participation_marks' => 2]);
        $this->validQuestion($quiz, marks: 3);

        $this->actingAs($lecturer)
            ->get(route('quizzes.review', $quiz))
            ->assertOk()
            ->assertSee('3 question marks')
            ->assertSee('= 5');
    }

    /**
     * @return array{User, Group, QuizCategory}
     */
    private function authoringContext(): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $group = Group::create([
            'Group_Name' => 'Lifecycle Group '.uniqid(),
            'Description' => 'Lifecycle tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Lifecycle Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        return [$lecturer, $group, $category];
    }

    private function studentIn(Group $group, ?QuizCategory $category = null): User
    {
        $student = User::factory()->create(['role' => 'student']);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        if ($category !== null) {
            CategoryStudent::create([
                'category_id' => $category->id,
                'user_id' => $student->id,
            ]);
        }

        return $student;
    }

    private function quiz(
        User $lecturer,
        Group $group,
        QuizCategory $category,
        array $overrides = [],
    ): Quiz {
        return Quiz::create(array_merge([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Lifecycle Quiz '.uniqid(),
            'description' => 'Lifecycle test quiz',
            'duration' => 15,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
            'status' => Quiz::STATUS_DRAFT,
            'created_by' => $lecturer->id,
        ], $overrides));
    }

    private function validQuestion(Quiz $quiz, int $marks = 1): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Valid question?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => $marks,
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

    private function questionPayload(Quiz $quiz, array $overrides = []): array
    {
        return array_merge([
            'quiz_id' => $quiz->id,
            'question' => 'Valid authored question?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 2,
            'options' => ['Correct', 'Wrong A', 'Wrong B', 'Wrong C'],
            'correct_option' => 0,
        ], $overrides);
    }
}
