<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizCategory;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizReportNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_index_averages_immutable_percentages_across_different_maxima(): void
    {
        [$lecturer, $group, $category] = $this->context();
        $first = $this->quiz($lecturer, $group, $category, ended: true);
        $second = $this->quiz($lecturer, $group, $category, ended: true);
        $legacy = $this->quiz($lecturer, $group, $category, ended: true);
        $firstStudent = $this->studentIn($group, 'First', 'Student');
        $secondStudent = $this->studentIn($group, 'Second', 'Student');
        $legacyStudent = $this->studentIn($group, 'Legacy', 'Student');
        $this->createResult($first, $firstStudent, total: 5, maximum: 10);
        $this->createResult($second, $secondStudent, total: 9, maximum: 30);
        QuizResult::create([
            'quiz_id' => $legacy->id,
            'user_id' => $legacyStudent->id,
            'score' => 100,
            'participation_marks' => 0,
            'total_score' => 100,
        ]);

        $this->actingAs($lecturer)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Average Percentage')
            ->assertSee('40%')
            ->assertSee('50%')
            ->assertSee('30%')
            ->assertSee('2 / 3')
            ->assertSee('snapshot unavailable');
    }

    public function test_lecturer_quiz_report_includes_non_submitters_timeouts_and_scoped_metrics(): void
    {
        [$lecturer, $group, $category] = $this->context();
        $quiz = $this->quiz($lecturer, $group, $category, ended: true);
        $submitted = $this->studentIn($group, 'Submitted', 'Learner');
        $timedOut = $this->studentIn($group, 'Timed', 'Out');
        $missing = $this->studentIn($group, 'No', 'Attempt');
        $this->createResult($quiz, $submitted, total: 8, maximum: 10);
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $timedOut->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinute(),
            'score' => 0,
            'status' => QuizAttempt::STATUS_AUTO_SUBMITTED,
        ]);
        $this->createResult($quiz, $timedOut, total: 0, maximum: 10, attempt: $attempt, auto: true);

        $response = $this->actingAs($lecturer)->get(route('reports.quiz', $quiz));
        $response->assertOk()
            ->assertSee($submitted->name)
            ->assertSee($timedOut->name)
            ->assertSee($missing->name)
            ->assertSee('Timed Out / Auto Submitted')
            ->assertSee('Not Attempted')
            ->assertSee('Average')
            ->assertSee('40%')
            ->assertSee('Pass Rate')
            ->assertSee('50%');

        $outsider = User::factory()->lecturer()->create();
        $this->actingAs($outsider)->get(route('reports.quiz', $quiz))->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee($quiz->title);
    }

    public function test_student_report_shows_personal_result_and_anonymized_thresholded_summary(): void
    {
        [$lecturer, $group, $category] = $this->context();
        $quiz = $this->quiz($lecturer, $group, $category, ended: true);
        $viewer = $this->studentIn($group, 'Private', 'Viewer');
        $others = collect();

        $this->createResult($quiz, $viewer, total: 8, maximum: 10);
        foreach (range(1, 4) as $index) {
            $student = $this->studentIn($group, "Hidden{$index}", 'Student');
            $others->push($student);
            $this->createResult($quiz, $student, total: $index + 4, maximum: 10);
        }

        $response = $this->actingAs($viewer)->get(route('quizzes.report', $quiz));
        $response->assertOk()
            ->assertSee('Personal result')
            ->assertSee('8 / 10')
            ->assertSee('80%')
            ->assertSee('Anonymized group summary')
            ->assertSee('Based on 5 comparable submissions');

        foreach ($others as $other) {
            $response->assertDontSee($other->name);
            $response->assertDontSee($other->email);
        }

        $nonSubmitter = $this->studentIn($group, 'No', 'Submission');
        $this->actingAs($nonSubmitter)
            ->get(route('quizzes.report', $quiz))
            ->assertOk()
            ->assertSee('You did not submit this quiz.')
            ->assertDontSee($viewer->name);

        $smallQuiz = $this->quiz($lecturer, $group, $category, ended: true);
        $this->createResult($smallQuiz, $viewer, total: 5, maximum: 10);
        foreach ($others->take(3) as $other) {
            $this->createResult($smallQuiz, $other, total: 5, maximum: 10);
        }

        $this->actingAs($viewer)
            ->get(route('quizzes.report', $smallQuiz))
            ->assertOk()
            ->assertSee('Aggregate performance is hidden until at least 5 comparable submissions exist.');
    }

    public function test_publish_notification_has_web_and_api_quiz_navigation_and_is_idempotent(): void
    {
        [$lecturer, $group, $category] = $this->context();
        $student = $this->studentIn($group, 'Notification', 'Student');
        $suspended = User::factory()->create(['role' => 'student']);
        $group->members()->attach($suspended->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $quiz = $this->quiz($lecturer, $group, $category);
        $this->validQuestion($quiz);

        $this->actingAs($lecturer)->patch(route('quizzes.publish', $quiz))->assertRedirect();
        $notification = Notification::firstOrFail();
        $notification->update(['Is_Read' => true]);
        $this->actingAs($lecturer)->patch(route('quizzes.publish', $quiz))->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
        $notification->refresh();
        $this->assertTrue($notification->Is_Read);
        $this->assertSame($quiz->id, $notification->quiz_id);
        $this->assertDatabaseMissing('notifications', [
            'user_ID' => $suspended->id,
            'quiz_id' => $quiz->id,
        ]);

        $token = $student->createToken('notification-api-test')->plainTextToken;
        app('auth')->forgetGuards();
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];

        $this->getJson('/api/notifications', $headers)
            ->assertOk()
            ->assertJsonPath('notifications.0.quiz_id', $quiz->id)
            ->assertJsonPath('notifications.0.url', route('student.quiz.show', $quiz));

        $this->actingAs($student)
            ->get(route('notifications.read', $notification))
            ->assertRedirect(route('student.quiz.show', $quiz));
    }

    public function test_deleted_quiz_nulls_notification_link_without_deleting_history(): void
    {
        [$lecturer, $group, $category] = $this->context();
        $student = $this->studentIn($group, 'Historical', 'Student');
        $quiz = $this->quiz($lecturer, $group, $category);
        $this->validQuestion($quiz);
        $this->actingAs($lecturer)->patch(route('quizzes.publish', $quiz))->assertRedirect();
        $notification = Notification::firstOrFail();

        $quiz->delete();

        $notification->refresh();
        $this->assertNull($notification->quiz_id);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $token = $student->createToken('deleted-quiz-notification')->plainTextToken;
        app('auth')->forgetGuards();
        $this->getJson('/api/notifications', [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('notifications.0.quiz_id', null)
            ->assertJsonPath('notifications.0.url', route('notifications.index'));
        $this->actingAs($student)
            ->get(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_newly_active_student_receives_relevant_quiz_notification_only_once(): void
    {
        [$lecturer, $group, $category] = $this->context(adminRole: true);
        $quiz = $this->quiz($lecturer, $group, $category);
        $this->validQuestion($quiz);
        $quiz->update(['status' => Quiz::STATUS_ACTIVE]);
        $student = User::factory()->create(['role' => 'student']);
        $suspended = User::factory()->create(['role' => 'student']);
        $group->members()->attach($suspended->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        $this->actingAs($lecturer)
            ->post(route('groups.members.add', $group), [
                'user_id' => $student->id,
                'Member_Role' => GroupMember::ROLE_MEMBER,
            ])
            ->assertRedirect(route('groups.show', $group));

        $this->assertDatabaseHas('notifications', [
            'user_ID' => $student->id,
            'quiz_id' => $quiz->id,
            'Notification_Type' => 'Quiz',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_ID' => $suspended->id,
            'quiz_id' => $quiz->id,
        ]);

        $this->actingAs($lecturer)
            ->post(route('groups.members.add', $group), [
                'user_id' => $student->id,
            ]);
        $this->assertSame(
            1,
            Notification::where('user_ID', $student->id)->where('quiz_id', $quiz->id)->count(),
        );
    }

    /**
     * @return array{User, Group, QuizCategory}
     */
    private function context(bool $adminRole = false): array
    {
        $lecturer = User::factory()->lecturer()->create();
        $group = Group::create([
            'Group_Name' => 'Report Group '.uniqid(),
            'Description' => 'Report tests',
            'Created_By' => $lecturer->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($lecturer->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => $adminRole ? GroupMember::ROLE_ADMIN : GroupMember::ROLE_LECTURER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Report Category '.uniqid(),
            'created_by' => $lecturer->id,
        ]);

        return [$lecturer, $group, $category];
    }

    private function studentIn(Group $group, string $first, string $last): User
    {
        $student = User::factory()->create([
            'Fname' => $first,
            'Lname' => $last,
            'role' => 'student',
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        return $student;
    }

    private function quiz(
        User $lecturer,
        Group $group,
        QuizCategory $category,
        bool $ended = false,
    ): Quiz {
        return Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Report Quiz '.uniqid(),
            'description' => 'Report and notification test',
            'duration' => 10,
            'participation_marks' => 2,
            'start_time' => now()->subHour(),
            'end_time' => $ended ? now()->subMinute() : now()->addHour(),
            'status' => $ended ? Quiz::STATUS_ACTIVE : Quiz::STATUS_DRAFT,
            'created_by' => $lecturer->id,
        ]);
    }

    private function validQuestion(Quiz $quiz): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Report question?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 8,
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

    private function createResult(
        Quiz $quiz,
        User $student,
        int $total,
        int $maximum,
        ?QuizAttempt $attempt = null,
        bool $auto = false,
    ): QuizResult {
        return QuizResult::create([
            'quiz_id' => $quiz->id,
            'attempt_id' => $attempt?->id,
            'user_id' => $student->id,
            'score' => max(0, $total - 2),
            'maximum_score' => max(0, $maximum - 2),
            'participation_marks' => min(2, $total),
            'total_score' => $total,
            'maximum_total_score' => $maximum,
            'grading_snapshot' => ['auto_submitted' => $auto],
            'graded_at' => now(),
        ]);
    }
}
