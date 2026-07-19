<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use App\Services\QuizSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_access_is_limited_to_actively_taught_groups(): void
    {
        $owner = User::factory()->lecturer()->create();
        $outsider = User::factory()->lecturer()->create();
        $group = $this->groupFor($owner);
        $quiz = $this->quizFor($owner, $group, ended: true);
        $question = $this->questionFor($quiz);

        $this->actingAs($owner)->get(route('quizzes.edit', $quiz))->assertOk();
        $this->actingAs($owner)->get(route('reports.quiz', $quiz))->assertOk();
        $this->actingAs($owner)->get(route('quizzes.index'))->assertSee($quiz->title);

        $this->actingAs($outsider)->get(route('quizzes.edit', $quiz))->assertForbidden();
        $this->actingAs($outsider)->get(route('questions.edit', $question))->assertForbidden();
        $this->actingAs($outsider)->get(route('reports.quiz', $quiz))->assertForbidden();
        $this->actingAs($outsider)->get(route('quizzes.index'))->assertDontSee($quiz->title);

        $group->members()->attach($outsider->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);

        $this->actingAs($outsider)->get(route('quizzes.edit', $quiz))->assertForbidden();
    }

    public function test_admin_can_manage_any_quiz_question_category_and_report(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $admin = User::factory()->admin()->create();
        $quiz = $this->quizFor($lecturer, $this->groupFor($lecturer), ended: true);
        $question = $this->questionFor($quiz);

        $this->actingAs($admin)->get(route('quizzes.edit', $quiz))->assertOk();
        $this->actingAs($admin)
            ->get(route('questions.edit', $question))
            ->assertRedirect(route('questions.index'))
            ->assertSessionHasErrors('question');
        $this->actingAs($admin)->get(route('quiz-categories.edit', $quiz->category))->assertOk();
        $this->actingAs($admin)->get(route('reports.quiz', $quiz))->assertOk();
    }

    public function test_question_cannot_be_moved_to_an_unauthorized_quiz(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $other = User::factory()->lecturer()->create();
        $question = $this->questionFor($this->quizFor($lecturer, $this->groupFor($lecturer)));
        $otherQuiz = $this->quizFor($other, $this->groupFor($other));

        $this->actingAs($lecturer)
            ->put(route('questions.update', $question), [
                'quiz_id' => $otherQuiz->id,
                'question' => 'Forged reassignment',
                'question_type' => 'Short Answer',
                'marks' => 1,
            ])
            ->assertForbidden();

        $this->assertSame($question->quiz_id, $question->fresh()->quiz_id);
    }

    public function test_quiz_cannot_be_moved_to_an_unauthorized_group(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $other = User::factory()->lecturer()->create();
        $quiz = $this->quizFor($lecturer, $this->groupFor($lecturer));
        $otherGroup = $this->groupFor($other);

        $this->actingAs($lecturer)
            ->put(route('quizzes.update', $quiz), [
                'category_id' => $quiz->category_id,
                'group_id' => $otherGroup->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'duration' => $quiz->duration,
                'participation_marks' => $quiz->participation_marks,
                'start_time' => $quiz->start_time->toDateTimeString(),
                'end_time' => $quiz->end_time->toDateTimeString(),
                'status' => $quiz->status,
            ])
            ->assertForbidden();

        $this->assertNotSame($otherGroup->id, $quiz->fresh()->group_id);
    }

    public function test_lecturers_can_only_edit_their_own_categories_and_cannot_delete_used_ones(): void
    {
        $owner = User::factory()->lecturer()->create();
        $other = User::factory()->lecturer()->create();
        $quiz = $this->quizFor($owner, $this->groupFor($owner));
        $category = $quiz->category;

        $this->actingAs($other)->get(route('quiz-categories.edit', $category))->assertForbidden();
        $this->actingAs($other)
            ->get(route('quiz-categories.index'))
            ->assertDontSee($category->category_name);
        $this->actingAs($owner)
            ->delete(route('quiz-categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('quiz_categories', ['id' => $category->id]);
    }

    public function test_student_routes_reject_lecturers_and_admins(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $admin = User::factory()->admin()->create();
        $quiz = $this->quizFor($lecturer, $this->groupFor($lecturer));
        $this->questionFor($quiz);

        $this->assertFalse($lecturer->can('viewAvailable', Quiz::class));
        $this->assertFalse($lecturer->can('take', $quiz));
        $this->actingAs($lecturer)->get(route('student.quizzes'))->assertForbidden();
        $this->actingAs($lecturer)->get(route('student.quiz.show', $quiz))->assertForbidden();
        $this->actingAs($lecturer)
            ->post(route('student.quiz.submit', $quiz), ['attempt_id' => 1])
            ->assertForbidden();
        $this->assertFalse($admin->can('viewAvailable', Quiz::class));
        $this->assertFalse($admin->can('take', $quiz));
        $this->actingAs($admin)->get(route('student.quizzes'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.quiz.show', $quiz))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('student.quiz.submit', $quiz), ['attempt_id' => 1])
            ->assertForbidden();
    }

    public function test_only_active_group_students_can_list_preview_submit_and_view_reports(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $active = User::factory()->create();
        $suspended = User::factory()->create();
        $inactive = User::factory()->create();
        $removed = User::factory()->create();
        $group = $this->groupFor($lecturer);
        $group->members()->attach($active->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $group->members()->attach($suspended->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $group->members()->attach($inactive->id, [
            'Member_Status' => 'Inactive',
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $quiz = $this->quizFor($lecturer, $group);
        $reportQuiz = $this->quizFor($lecturer, $group, ended: true);
        $this->questionFor($quiz);

        $this->actingAs($active)->get(route('student.quizzes'))->assertSee($quiz->title);
        $this->actingAs($active)->get(route('student.quiz.show', $quiz))->assertOk();
        $this->actingAs($active)->get(route('quizzes.report', $reportQuiz))->assertOk();
        $this->actingAs($suspended)->get(route('student.quizzes'))->assertDontSee($quiz->title);
        $this->actingAs($suspended)->get(route('student.quiz.show', $quiz))->assertForbidden();
        $this->actingAs($suspended)->get(route('quizzes.report', $reportQuiz))->assertForbidden();
        $this->actingAs($inactive)->get(route('student.quizzes'))->assertDontSee($quiz->title);
        $this->actingAs($inactive)->get(route('student.quiz.show', $quiz))->assertForbidden();
        $this->actingAs($inactive)->get(route('quizzes.report', $reportQuiz))->assertForbidden();
        $this->actingAs($removed)->get(route('student.quizzes'))->assertDontSee($quiz->title);
        $this->actingAs($removed)->get(route('student.quiz.show', $quiz))->assertForbidden();
        $this->actingAs($removed)->get(route('quizzes.report', $reportQuiz))->assertForbidden();

        $attempt = app(QuizSubmissionService::class)->startAttempt($active, $quiz);
        $group->members()->updateExistingPivot($active->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
        ]);

        $this->actingAs($active)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [],
            ])
            ->assertForbidden();
        $this->assertDatabaseCount('quiz_results', 0);

        $quiz->update(['end_time' => now()->subMinute()]);
        $this->actingAs($active)->get(route('quizzes.report', $quiz))->assertForbidden();
    }

    public function test_students_cannot_access_cross_group_or_global_quizzes_and_reports(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->create();
        $assignedGroup = $this->groupFor($lecturer);
        $otherGroup = $this->groupFor($lecturer);
        $assignedGroup->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        $otherQuiz = $this->quizFor($lecturer, $otherGroup, ended: true);
        $globalQuiz = $this->quizFor($lecturer, null, ended: true);
        $this->questionFor($otherQuiz);
        $this->questionFor($globalQuiz);

        $response = $this->actingAs($student)->get(route('student.quizzes'));
        $response->assertDontSee($otherQuiz->title)->assertDontSee($globalQuiz->title);

        $this->actingAs($student)->get(route('student.quiz.show', $otherQuiz))->assertForbidden();
        $this->actingAs($student)
            ->get(route('student.quiz.show', ['quiz' => $otherQuiz, 'start' => 1]))
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.quiz.submit', $otherQuiz), ['attempt_id' => 1])
            ->assertForbidden();
        $this->actingAs($student)->get(route('student.quiz.show', $globalQuiz))->assertForbidden();
        $this->actingAs($student)->get(route('quizzes.report', $otherQuiz))->assertForbidden();
        $this->actingAs($student)->get(route('quizzes.report', $globalQuiz))->assertForbidden();
    }

    private function groupFor(User $lecturer): Group
    {
        $group = Group::create([
            'Group_Name' => 'Authorization Group '.uniqid(),
            'Description' => 'Quiz authorization tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);

        return $group;
    }

    private function quizFor(User $creator, ?Group $group, bool $ended = false): Quiz
    {
        $category = QuizCategory::create([
            'category_name' => 'Authorization Category '.uniqid(),
            'created_by' => $creator->id,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group?->id,
            'title' => 'Authorization Quiz '.uniqid(),
            'description' => 'Authorization test quiz',
            'duration' => 10,
            'participation_marks' => 1,
            'start_time' => now()->subHour(),
            'end_time' => $ended ? now()->subMinute() : now()->addHour(),
            'status' => 'Active',
            'created_by' => $creator->id,
        ]);
    }

    private function questionFor(Quiz $quiz): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Authorization question?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 1,
        ]);

        foreach (['Correct', 'Distractor A', 'Distractor B', 'Distractor C'] as $index => $text) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $text,
                'is_correct' => $index === 0,
            ]);
        }

        return $question;
    }
}
