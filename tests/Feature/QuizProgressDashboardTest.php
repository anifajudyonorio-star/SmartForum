<?php

namespace Tests\Feature;

use App\Models\CategoryStudent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizProgressDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_dashboard_is_student_only_with_policy_defense(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->get(route('student.quizzes.progress'))
            ->assertRedirect(route('login'));
        $this->actingAs($lecturer)
            ->get(route('student.quizzes.progress'))
            ->assertForbidden();
        $this->assertFalse($lecturer->can('viewProgress', Quiz::class));
    }

    public function test_progress_is_private_and_calculates_only_comparable_owned_results(): void
    {
        $student = User::factory()->create();
        $peer = User::factory()->create();
        $group = $this->groupWithStudents($student, $peer);
        $category = QuizCategory::create([
            'category_name' => 'Progress Category '.uniqid(),
            'created_by' => $student->id,
        ]);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $olderQuiz = $this->quiz($student, $group, 'Repeated title', $category);
        $newerQuiz = $this->quiz($student, $group, 'Repeated title', $category);
        $legacyQuiz = $this->quiz($student, $group, 'Legacy denominator', $category);
        $peerQuiz = $this->quiz($student, $group, 'Peer secret quiz', $category);

        $older = $this->createResult(
            $student,
            $olderQuiz,
            total: 5,
            maximum: 10,
            gradedAt: '2026-01-01 10:00:00',
        );
        $newer = $this->createResult(
            $student,
            $newerQuiz,
            total: 9,
            maximum: 30,
            gradedAt: '2026-01-03 10:00:00',
            autoSubmitted: true,
        );
        $this->createResult(
            $student,
            $legacyQuiz,
            total: 8,
            maximum: null,
            gradedAt: '2026-01-04 10:00:00',
        );
        $this->createResult(
            $peer,
            $peerQuiz,
            total: 10,
            maximum: 10,
            gradedAt: '2026-01-05 10:00:00',
        );

        $response = $this->actingAs($student)
            ->get(route('student.quizzes.progress'))
            ->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                return $summary === [
                    'total_attempted' => 3,
                    'comparable_attempts' => 2,
                    'average_percentage' => 40.0,
                    'highest_percentage' => 50.0,
                    'latest_percentage' => 30.0,
                    'pass_rate' => 50.0,
                    'trend' => -20.0,
                ];
            })
            ->assertViewHas('chartData', [50.0, 30.0])
            ->assertViewHas('chartLabels', function (array $labels) use ($older, $newer) {
                return count($labels) === 2
                    && str_contains($labels[0], "Repeated title · Jan 1, 2026 · Result #{$older->id}")
                    && str_contains($labels[1], "Repeated title · Jan 3, 2026 · Result #{$newer->id}");
            })
            ->assertSee('Timed Out / Auto Submitted')
            ->assertSee('Legacy denominator')
            ->assertSee('Not comparable')
            ->assertSee('1 legacy result is')
            ->assertSee(route('quizzes.report', $olderQuiz), false)
            ->assertSee(route('quizzes.report', $newerQuiz), false)
            ->assertDontSee('Peer secret quiz')
            ->assertDontSee($peer->name);

        $history = $response->viewData('history');
        $this->assertCount(3, $history);
        $this->assertTrue($history->every(
            fn (array $row) => $row['result']->user_id === $student->id,
        ));
        $this->assertSame(
            ['Legacy denominator', 'Repeated title', 'Repeated title'],
            $history->pluck('quiz.title')->all(),
        );
    }

    public function test_empty_progress_dashboard_has_safe_empty_state(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('student.quizzes.progress'))
            ->assertOk()
            ->assertSee('No quiz history yet.')
            ->assertSee('No comparable percentage data is available yet.')
            ->assertViewHas('chartData', [])
            ->assertViewHas('summary', fn (array $summary) => $summary['total_attempted'] === 0
                && $summary['average_percentage'] === null
                && $summary['trend'] === null);
    }

    public function test_one_attempt_with_malformed_legacy_dates_renders_safely(): void
    {
        $student = User::factory()->create();
        $group = $this->groupWithStudents($student);
        $quiz = $this->quiz($student, $group, 'Malformed date quiz');
        $result = $this->createResult(
            $student,
            $quiz,
            total: 7,
            maximum: 10,
            gradedAt: '2026-01-02 10:00:00',
        );

        DB::table('quiz_results')->where('id', $result->id)->update([
            'graded_at' => 'not-a-date',
            'created_at' => 'also-not-a-date',
        ]);
        DB::table('quiz_attempts')->where('id', $result->attempt_id)->update([
            'submitted_at' => 'invalid-attempt-date',
        ]);

        $this->actingAs($student)
            ->get(route('student.quizzes.progress'))
            ->assertOk()
            ->assertSee('Malformed date quiz')
            ->assertSee('Date unavailable')
            ->assertSee('70%')
            ->assertSee('Two comparable attempts are needed to calculate change.')
            ->assertViewHas('chartData', [70.0]);
    }

    private function groupWithStudents(User ...$students): Group
    {
        $group = Group::create([
            'Group_Name' => 'Progress Group '.uniqid(),
            'Description' => 'Student progress tests',
            'Created_By' => $students[0]->id,
            'Status' => 'Active',
        ]);

        foreach ($students as $student) {
            $group->members()->attach($student->id, [
                'Member_Status' => GroupMember::STATUS_ACTIVE,
                'Member_Role' => GroupMember::ROLE_MEMBER,
            ]);
        }

        return $group;
    }

    private function quiz(
        User $creator,
        Group $group,
        string $title,
        ?QuizCategory $category = null,
    ): Quiz {
        $category ??= QuizCategory::create([
            'category_name' => 'Progress Category '.uniqid(),
            'created_by' => $creator->id,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => $title,
            'description' => 'Progress dashboard quiz',
            'duration' => 10,
            'participation_marks' => 2,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $creator->id,
        ]);
    }

    private function createResult(
        User $student,
        Quiz $quiz,
        int $total,
        ?int $maximum,
        string $gradedAt,
        bool $autoSubmitted = false,
    ): QuizResult {
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'started_at' => $gradedAt,
            'submitted_at' => $gradedAt,
            'score' => $total,
            'status' => $autoSubmitted
                ? QuizAttempt::STATUS_AUTO_SUBMITTED
                : QuizAttempt::STATUS_SUBMITTED,
        ]);

        return QuizResult::create([
            'quiz_id' => $quiz->id,
            'attempt_id' => $attempt->id,
            'user_id' => $student->id,
            'score' => max(0, $total - 2),
            'maximum_score' => $maximum === null ? null : max(0, $maximum - 2),
            'participation_marks' => 2,
            'total_score' => $total,
            'maximum_total_score' => $maximum,
            'grading_snapshot' => ['auto_submitted' => $autoSubmitted],
            'graded_at' => $gradedAt,
        ]);
    }
}
