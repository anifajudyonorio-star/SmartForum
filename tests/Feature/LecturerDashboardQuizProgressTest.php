<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LecturerDashboardQuizProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_dashboard_shows_scoped_quiz_progress_stats_and_hides_foreign_results(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $outsider = User::factory()->lecturer()->create();
        $student = User::factory()->create(['role' => 'student', 'Fname' => 'Tracked', 'Lname' => 'Student']);
        $foreignStudent = User::factory()->create(['role' => 'student', 'Fname' => 'Foreign', 'Lname' => 'Learner']);

        $ownedGroup = $this->groupFor($lecturer);
        $foreignGroup = $this->groupFor($outsider);
        $ownedGroup->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $foreignGroup->members()->attach($foreignStudent->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        $ownedQuiz = $this->quiz($lecturer, $ownedGroup, 'Owned Progress Quiz');
        $foreignQuiz = $this->quiz($outsider, $foreignGroup, 'Foreign Secret Quiz');
        $this->createOwnedResult($ownedQuiz, $student, total: 8, maximum: 10);
        $this->createOwnedResult($foreignQuiz, $foreignStudent, total: 10, maximum: 10);

        $response = $this->actingAs($lecturer)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Student Quiz Progress')
            ->assertSee('Owned Progress Quiz')
            ->assertSee('Tracked Student')
            ->assertSee('80%')
            ->assertDontSee('Foreign Secret Quiz')
            ->assertDontSee('Foreign Learner')
            ->assertViewHas('quizProgress', function (array $progress) {
                return $progress['summary']['submissions'] === 1
                    && $progress['summary']['students_assessed'] === 1
                    && $progress['summary']['average_percentage'] === 80.0
                    && $progress['summary']['pass_rate'] === 100.0
                    && $progress['quizAverages']['values'] === [80.0]
                    && $progress['distribution']['Excellent (80%+)'] === 1;
            });
    }

    private function groupFor(User $lecturer): Group
    {
        $group = Group::create([
            'Group_Name' => 'Dashboard Group '.uniqid(),
            'Description' => 'Lecturer dashboard tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_LECTURER,
        ]);

        return $group;
    }

    private function quiz(User $lecturer, Group $group, string $title): Quiz
    {
        $category = QuizCategory::create([
            'category_name' => 'Dashboard Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => $title,
            'description' => 'Dashboard progress quiz',
            'duration' => 10,
            'participation_marks' => 0,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $lecturer->id,
        ]);
    }

    private function createOwnedResult(Quiz $quiz, User $student, int $total, int $maximum): QuizResult
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
            'status' => QuizAttempt::STATUS_SUBMITTED,
        ]);

        return QuizResult::create([
            'quiz_id' => $quiz->id,
            'attempt_id' => $attempt->id,
            'user_id' => $student->id,
            'score' => $total,
            'maximum_score' => $maximum,
            'participation_marks' => 0,
            'total_score' => $total,
            'maximum_total_score' => $maximum,
            'graded_at' => now()->subMinutes(30),
        ]);
    }
}
