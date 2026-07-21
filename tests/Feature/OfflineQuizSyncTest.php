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
use App\Models\QuizResult;
use App\Models\SyncQueue;
use App\Models\User;
use App\Services\QuizSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineQuizSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_submission_uses_service_grading_and_owned_attempt(): void
    {
        $fixture = $this->fixture();
        $uuid = '30000000-0000-4000-8000-000000000001';

        $this->uploadQuizAction($fixture, $uuid, [
            $fixture['question']->id => $fixture['correct']->id,
        ])->assertOk()->assertJsonPath('actions.0.status', 'queued');

        $this->sync($fixture)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'succeeded');

        $result = QuizResult::firstOrFail();
        $this->assertSame($fixture['attempt']->id, $result->attempt_id);
        $this->assertSame(3, $result->score);
        $this->assertSame(5, $result->total_score);
        $this->assertSame(QuizAttempt::STATUS_SUBMITTED, $fixture['attempt']->fresh()->status);
    }

    public function test_missing_and_wrong_attempts_are_rejected(): void
    {
        $fixture = $this->fixture();

        $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => '30000000-0000-4000-8000-000000000002',
                'action_type' => 'submit_quiz',
                'payload' => [
                    'quiz_id' => $fixture['quiz']->id,
                    'answers' => [],
                ],
            ]],
        ], $fixture['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('actions.0.payload.attempt_id');
        $this->assertDatabaseCount('sync_queue', 0);

        $other = $this->studentIn($fixture['group']);
        CategoryStudent::create([
            'category_id' => $fixture['quiz']->category_id,
            'user_id' => $other->id,
        ]);
        $otherAttempt = app(QuizSubmissionService::class)->startAttempt($other, $fixture['quiz']);
        $this->uploadQuizAction(
            $fixture,
            '30000000-0000-4000-8000-000000000003',
            [],
            $otherAttempt->id,
        )->assertOk();

        $this->sync($fixture)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed');
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_pre_start_submission_fails_closed(): void
    {
        $fixture = $this->fixture(started: false, createAttempt: false);
        $attempt = QuizAttempt::create([
            'quiz_id' => $fixture['quiz']->id,
            'user_id' => $fixture['student']->id,
            'started_at' => now(),
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
        ]);

        $this->uploadQuizAction(
            $fixture,
            '30000000-0000-4000-8000-000000000004',
            [],
            $attempt->id,
        )->assertOk();

        $this->sync($fixture)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed')
            ->assertJsonPath('conflicts.0.reason', 'This quiz is not currently available.');
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_suspended_member_submission_is_retained_as_failed(): void
    {
        $fixture = $this->fixture();
        $fixture['group']->members()->updateExistingPivot($fixture['student']->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
        ]);

        $this->uploadQuizAction(
            $fixture,
            '30000000-0000-4000-8000-000000000005',
            [],
        )->assertOk();

        $this->sync($fixture)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed');

        $action = SyncQueue::firstOrFail();
        $this->assertFalse($action->is_synced);
        $this->assertSame(SyncQueue::STATUS_FAILED, $action->sync_status);
        $this->assertStringContainsString('assigned', $action->last_error);
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_non_student_cannot_sync_a_quiz_submission(): void
    {
        $fixture = $this->fixture();
        $lecturer = User::factory()->lecturer()->create();
        $this->assertFalse($lecturer->isStudent());
        $token = $lecturer->createToken('offline-lecturer-test')->plainTextToken;
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
        app('auth')->forgetGuards();
        $deviceId = 'offline-lecturer-device';
        $this->postJson('/api/sync/device', [
            'device_id' => $deviceId,
            'device_name' => 'Lecturer Device',
        ], $headers)->assertOk();
        $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => '30000000-0000-4000-8000-000000000012',
                'action_type' => 'submit_quiz',
                'payload' => [
                    'quiz_id' => $fixture['quiz']->id,
                    'attempt_id' => $fixture['attempt']->id,
                    'answers' => [],
                ],
            ]],
        ], $headers)->assertOk();
        $this->assertDatabaseHas('sync_queue', [
            'user_id' => $lecturer->id,
            'action_type' => 'submit_quiz',
        ]);

        $this->postJson('/api/sync', ['device_id' => $deviceId], $headers)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed')
            ->assertJsonPath('conflicts.0.reason', 'Only students can take quizzes.');
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_forged_option_is_rejected_without_mutating_attempt(): void
    {
        $fixture = $this->fixture();
        $otherQuestion = $this->validQuestion($fixture['quiz'], 'Other question');
        $forgedOption = $otherQuestion->options()->where('is_correct', true)->firstOrFail();

        $this->uploadQuizAction(
            $fixture,
            '30000000-0000-4000-8000-000000000006',
            [$fixture['question']->id => $forgedOption->id],
        )->assertOk();

        $this->sync($fixture)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed');

        $this->assertSame(QuizAttempt::STATUS_IN_PROGRESS, $fixture['attempt']->fresh()->status);
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_replayed_action_uuid_and_lost_response_retry_are_idempotent(): void
    {
        $fixture = $this->fixture();
        $uuid = '30000000-0000-4000-8000-000000000007';
        $answers = [$fixture['question']->id => $fixture['correct']->id];

        $this->uploadQuizAction($fixture, $uuid, $answers)->assertJsonPath('actions.0.status', 'queued');
        $this->sync($fixture)->assertJsonPath('actions.0.status', 'succeeded');

        // Simulate a lost successful response: the browser uploads the same local action again.
        $this->uploadQuizAction($fixture, $uuid, $answers)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'duplicate');
        $this->uploadQuizAction($fixture, $uuid, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action_uuid');

        $this->assertDatabaseCount('sync_queue', 1);
        $this->assertDatabaseCount('quiz_results', 1);
        $this->assertDatabaseCount('quiz_attempt_answers', 1);
    }

    public function test_expired_offline_answers_are_ignored_and_attempt_is_auto_submitted(): void
    {
        $startedAt = CarbonImmutable::parse('2026-07-17 10:00:00');
        $this->travelTo($startedAt);
        $fixture = $this->fixture(duration: 1);
        $this->travelTo($startedAt->addMinutes(2));

        $this->uploadQuizAction(
            $fixture,
            '30000000-0000-4000-8000-000000000008',
            [$fixture['question']->id => $fixture['correct']->id],
        )->assertOk();
        $this->sync($fixture)->assertJsonPath('actions.0.status', 'succeeded');

        $result = QuizResult::firstOrFail();
        $this->assertSame(0, $result->score);
        $this->assertSame(0, $result->total_score);
        $this->assertTrue($result->grading_snapshot['auto_submitted']);
        $this->assertSame(QuizAttempt::STATUS_AUTO_SUBMITTED, $fixture['attempt']->fresh()->status);
    }

    public function test_failed_action_remains_actionable_across_retries(): void
    {
        $fixture = $this->fixture();
        $uuid = '30000000-0000-4000-8000-000000000009';
        $this->uploadQuizAction($fixture, $uuid, [], 999999)->assertOk();
        $this->sync($fixture)->assertJsonPath('actions.0.status', 'failed');

        $this->uploadQuizAction($fixture, $uuid, [], 999999)
            ->assertOk()
            ->assertJsonPath('actions.0.status', 'failed');
        $this->sync($fixture)->assertJsonPath('actions.0.status', 'failed');

        $action = SyncQueue::firstOrFail();
        $this->assertFalse($action->is_synced);
        $this->assertSame(SyncQueue::STATUS_FAILED, $action->sync_status);
        $this->assertNotNull($action->last_error);
        $this->assertDatabaseCount('sync_queue', 1);
        $this->assertDatabaseCount('quiz_results', 0);
    }

    public function test_unknown_or_oversized_action_payload_is_not_queued(): void
    {
        $fixture = $this->fixture();

        $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => '30000000-0000-4000-8000-000000000010',
                'action_type' => 'delete_everything',
                'payload' => [],
            ]],
        ], $fixture['headers'])->assertUnprocessable();

        $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => '30000000-0000-4000-8000-000000000011',
                'action_type' => 'create_post',
                'payload' => [
                    'topic_id' => 1,
                    'content' => str_repeat('x', 66000),
                ],
            ]],
        ], $fixture['headers'])->assertUnprocessable();

        $this->assertDatabaseCount('sync_queue', 0);
    }

    /**
     * @return array{
     *     student: User,
     *     group: Group,
     *     quiz: Quiz,
     *     question: Question,
     *     correct: QuestionOption,
     *     attempt: QuizAttempt|null,
     *     headers: array<string, string>,
     *     device_id: string
     * }
     */
    private function fixture(
        bool $started = true,
        bool $createAttempt = true,
        int $duration = 10,
    ): array {
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::create([
            'Group_Name' => 'Offline Quiz Group '.uniqid(),
            'Created_By' => $student->id,
            'Status' => 'Active',
        ]);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);
        $category = QuizCategory::create([
            'category_name' => 'Offline Quiz Category '.uniqid(),
            'created_by' => $student->id,
        ]);
        CategoryStudent::create([
            'category_id' => $category->id,
            'user_id' => $student->id,
        ]);
        $quiz = Quiz::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Offline Quiz '.uniqid(),
            'description' => 'Offline synchronization test',
            'duration' => $duration,
            'participation_marks' => 2,
            'start_time' => $started ? now()->subMinute() : now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => $started ? Quiz::STATUS_ACTIVE : Quiz::STATUS_SCHEDULED,
            'created_by' => $student->id,
        ]);
        $question = $this->validQuestion($quiz);
        $correct = $question->options()->where('is_correct', true)->firstOrFail();
        $attempt = $createAttempt
            ? app(QuizSubmissionService::class)->startAttempt($student, $quiz)
            : null;
        $token = $student->createToken('offline-quiz-test')->plainTextToken;
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
        $deviceId = 'offline-device-'.uniqid();

        $this->postJson('/api/sync/device', [
            'device_id' => $deviceId,
            'device_name' => 'Offline Test Device',
        ], $headers)->assertOk();

        return compact(
            'student',
            'group',
            'quiz',
            'question',
            'correct',
            'attempt',
            'headers',
        ) + ['device_id' => $deviceId];
    }

    private function studentIn(Group $group): User
    {
        $student = User::factory()->create(['role' => 'student']);
        $group->members()->attach($student->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        return $student;
    }

    private function validQuestion(Quiz $quiz, string $text = 'Offline question?'): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question' => $text,
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'marks' => 3,
        ]);

        foreach (['Correct', 'Wrong A', 'Wrong B', 'Wrong C'] as $index => $optionText) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $optionText,
                'is_correct' => $index === 0,
            ]);
        }

        return $question;
    }

    private function uploadQuizAction(
        array $fixture,
        string $uuid,
        array $answers,
        ?int $attemptId = null,
    ) {
        return $this->postJson('/api/sync/upload', [
            'actions' => [[
                'action_uuid' => $uuid,
                'action_type' => 'submit_quiz',
                'payload' => [
                    'quiz_id' => $fixture['quiz']->id,
                    'attempt_id' => $attemptId ?? $fixture['attempt']->id,
                    'answers' => $answers,
                ],
            ]],
        ], $fixture['headers']);
    }

    private function sync(array $fixture)
    {
        return $this->postJson('/api/sync', [
            'device_id' => $fixture['device_id'],
        ], $fixture['headers']);
    }
}
