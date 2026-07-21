<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_self_enroll_and_see_only_that_category_quizzes(): void
    {
        [$lecturer, $student, $group, $category, $quiz] = $this->fixtures();
        $otherCategory = QuizCategory::create([
            'category_name' => 'Other Category',
            'created_by' => $lecturer->id,
        ]);
        $otherQuiz = Quiz::create([
            'category_id' => $otherCategory->id,
            'group_id' => $group->id,
            'title' => 'Other Category Quiz',
            'description' => 'Should stay hidden',
            'duration' => 10,
            'participation_marks' => 0,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $lecturer->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.quizzes'))
            ->assertOk()
            ->assertDontSee($quiz->title)
            ->assertSee('Enroll Me');

        $this->actingAs($student)
            ->post(route('student.quizzes.enroll'), ['category_id' => $category->id])
            ->assertRedirect(route('student.quizzes'));

        $this->assertDatabaseHas('category_students', [
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.quizzes'))
            ->assertOk()
            ->assertSee($quiz->title)
            ->assertDontSee($otherQuiz->title)
            ->assertSee($category->category_name);

        $this->actingAs($student)
            ->post(route('student.quizzes.enroll'), ['category_id' => $otherCategory->id])
            ->assertRedirect(route('student.quizzes'))
            ->assertSessionHasErrors('category_id');
    }

    public function test_student_cannot_take_quiz_without_category_enrollment(): void
    {
        [, $student, , , $quiz] = $this->fixtures();

        $this->actingAs($student)
            ->get(route('student.quiz.show', $quiz))
            ->assertForbidden();
    }

    public function test_lecturer_can_enroll_and_unenroll_group_students(): void
    {
        [$lecturer, $student, , $category] = $this->fixtures();

        $this->actingAs($lecturer)
            ->get(route('category-enrollments.index', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee($category->category_name)
            ->assertSee($student->email);

        $this->actingAs($lecturer)
            ->post(route('category-enrollments.store'), [
                'category_id' => $category->id,
                'user_id' => $student->id,
            ])
            ->assertRedirect(route('category-enrollments.index', ['category_id' => $category->id]));

        $this->assertDatabaseHas('category_students', [
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($lecturer)
            ->delete(route('category-enrollments.destroy'), [
                'category_id' => $category->id,
                'user_id' => $student->id,
            ])
            ->assertRedirect(route('category-enrollments.index', ['category_id' => $category->id]));

        $this->assertDatabaseMissing('category_students', [
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_lecturer_cannot_manage_another_lecturers_category_enrollment(): void
    {
        [$owner, $student, , $category] = $this->fixtures();
        $outsider = User::factory()->lecturer()->create();

        $this->actingAs($outsider)
            ->post(route('category-enrollments.store'), [
                'category_id' => $category->id,
                'user_id' => $student->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('category_students', [
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $this->assertTrue($owner->isLecturer());
    }

    public function test_one_student_may_belong_to_only_one_category(): void
    {
        [$lecturer, $student, , $category] = $this->fixtures();
        $otherCategory = QuizCategory::create([
            'category_name' => 'Second Category',
            'created_by' => $lecturer->id,
        ]);

        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($lecturer)
            ->post(route('category-enrollments.store'), [
                'category_id' => $otherCategory->id,
                'user_id' => $student->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, CategoryStudent::where('user_id', $student->id)->count());
    }

    /**
     * @return array{0: User, 1: User, 2: Group, 3: QuizCategory, 4: Quiz}
     */
    private function fixtures(): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::create([
            'Group_Name' => 'Enrollment Group '.uniqid(),
            'Description' => 'Enrollment tests',
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
            'category_name' => 'Enrollment Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);
        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Enrollment Quiz '.uniqid(),
            'description' => 'Enrollment test quiz',
            'duration' => 10,
            'participation_marks' => 0,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $lecturer->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Enrollment question?',
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

        return [$lecturer, $student, $group, $category, $quiz];
    }
}
