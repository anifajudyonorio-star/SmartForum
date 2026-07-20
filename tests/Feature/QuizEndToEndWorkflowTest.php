<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizEndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_authoring_to_private_student_report_workflow(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->create([
            'Fname' => 'End',
            'Lname' => 'ToEnd',
            'role' => 'student',
        ]);
        $group = Group::create([
            'Group_Name' => 'End-to-End Group',
            'Description' => 'Release workflow',
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
            'category_name' => 'End-to-End Category',
            'created_by' => $lecturer->id,
        ]);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $start = now()->subMinute();
        $end = now()->addHour();

        $this->actingAs($lecturer)
            ->post(route('quizzes.store'), [
                'category_id' => $category->id,
                'group_id' => $group->id,
                'title' => 'End-to-End Quiz',
                'description' => 'Complete release workflow',
                'duration' => 10,
                'participation_marks' => 2,
                'start_time' => $start->toDateTimeString(),
                'end_time' => $end->toDateTimeString(),
            ])
            ->assertRedirect(route('quizzes.index'));

        $quiz = Quiz::where('title', 'End-to-End Quiz')->firstOrFail();
        $this->assertSame(Quiz::STATUS_DRAFT, $quiz->status);

        $this->actingAs($lecturer)
            ->post(route('questions.store'), [
                'quiz_id' => $quiz->id,
                'question' => 'Which answer is correct?',
                'question_type' => Question::TYPE_MULTIPLE_CHOICE,
                'marks' => 4,
                'options' => ['Correct', 'Wrong A', 'Wrong B', 'Wrong C'],
                'correct_option' => 0,
            ])
            ->assertRedirect(route('questions.index'));

        $question = $quiz->questions()->with('options')->firstOrFail();
        $correct = $question->options->firstWhere('is_correct', true);

        $this->actingAs($lecturer)
            ->patch(route('quizzes.publish', $quiz))
            ->assertRedirect();
        $this->assertSame(Quiz::STATUS_ACTIVE, $quiz->fresh()->status);

        $this->actingAs($student)
            ->get(route('student.quizzes'))
            ->assertOk()
            ->assertSee('End-to-End Quiz');
        $this->actingAs($student)
            ->get(route('student.quiz.show', $quiz))
            ->assertOk()
            ->assertSee('Maximum score');
        $this->actingAs($student)
            ->get(route('student.quiz.show', ['quiz' => $quiz, 'start' => 1]))
            ->assertOk()
            ->assertSee('Which answer is correct?');

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->firstOrFail();
        $this->actingAs($student)
            ->post(route('student.quiz.submit', $quiz), [
                'attempt_id' => $attempt->id,
                'answers' => [$question->id => $correct->id],
            ])
            ->assertOk()
            ->assertSee('6 / 6')
            ->assertSee('100%');

        $result = QuizResult::firstOrFail();
        $this->assertSame(4, $result->score);
        $this->assertSame(6, $result->maximum_total_score);

        $this->travelTo($end->addSecond());

        $this->actingAs($lecturer)
            ->get(route('reports.quiz', $quiz))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('100%');
        $this->actingAs($student)
            ->get(route('quizzes.report', $quiz))
            ->assertOk()
            ->assertSee('Personal result')
            ->assertSee('6 / 6')
            ->assertSee('Aggregate performance is hidden until at least 5 comparable submissions exist.')
            ->assertDontSee('Student Results');
    }
}
