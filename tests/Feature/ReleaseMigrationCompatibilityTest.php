<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReleaseMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_migration_is_safe_when_schema_already_exists(): void
    {
        $migration = require database_path(
            'migrations/2026_07_17_200000_add_idempotency_to_sync_queue.php',
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumn('sync_queue', 'action_uuid'));
        $this->assertTrue(Schema::hasColumn('sync_queue', 'sync_status'));
        $this->assertTrue(Schema::hasColumn('sync_queue', 'last_error'));
        $matchingIndexes = collect(Schema::getIndexes('sync_queue'))
            ->filter(fn ($index) => $index['unique']
                && $index['columns'] === ['user_id', 'action_uuid']);
        $this->assertCount(1, $matchingIndexes);
    }

    public function test_notification_upgrade_deduplicates_rows_and_is_rerunnable(): void
    {
        $migration = require database_path(
            'migrations/2026_07_17_210000_harden_quiz_notifications.php',
        );
        $migration->down();

        $user = User::factory()->create();
        $quiz = $this->quiz($user);
        $attributes = [
            'user_ID' => $user->id,
            'Notification_Type' => 'Quiz',
            'Notification_Title' => 'Legacy duplicate',
            'Message' => 'Legacy notification',
            'Is_Read' => false,
            'quiz_id' => $quiz->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('notifications')->insert([$attributes, $attributes]);

        $migration->up();
        $migration->up();

        $this->assertSame(
            1,
            DB::table('notifications')
                ->where('user_ID', $user->id)
                ->where('quiz_id', $quiz->id)
                ->where('Notification_Type', 'Quiz')
                ->count(),
        );

        $quiz->delete();
        $this->assertDatabaseHas('notifications', [
            'Notification_Title' => 'Legacy duplicate',
            'quiz_id' => null,
        ]);
    }

    private function quiz(User $creator): Quiz
    {
        $category = QuizCategory::create([
            'category_name' => 'Release Migration '.uniqid(),
            'created_by' => $creator->id,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'title' => 'Release Migration Quiz '.uniqid(),
            'description' => 'Migration compatibility test',
            'duration' => 10,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
            'status' => Quiz::STATUS_ACTIVE,
            'created_by' => $creator->id,
        ]);
    }
}
