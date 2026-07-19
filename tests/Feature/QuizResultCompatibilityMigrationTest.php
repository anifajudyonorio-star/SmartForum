<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizCategory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuizResultCompatibilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_only_results_are_migrated_and_duplicates_are_archived(): void
    {
        $user = User::factory()->create();
        $quiz = $this->createQuiz($user);
        $this->createLegacyTable(includeUserId: false);

        DB::table('quiz_results')->insert([
            $this->legacyResult($quiz->id, $user->id, score: 4),
            $this->legacyResult($quiz->id, $user->id, score: 2),
        ]);

        $this->runCompatibilityMigration();

        $this->assertTrue(Schema::hasColumn('quiz_results', 'user_id'));
        $this->assertFalse(Schema::hasColumn('quiz_results', 'student_id'));
        $this->assertDatabaseCount('quiz_results', 1);
        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => 4,
        ]);
        $this->assertDatabaseCount('quiz_result_duplicate_archives', 1);
    }

    public function test_existing_user_id_wins_when_both_columns_disagree(): void
    {
        $legacyStudent = User::factory()->create();
        $canonicalUser = User::factory()->create();
        $quiz = $this->createQuiz($canonicalUser);
        $this->createLegacyTable(includeUserId: true);

        DB::table('quiz_results')->insert([
            'quiz_id' => $quiz->id,
            'student_id' => $legacyStudent->id,
            'user_id' => $canonicalUser->id,
            'score' => 3,
            'participation_marks' => 2,
            'total_score' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runCompatibilityMigration();

        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'user_id' => $canonicalUser->id,
        ]);
        $this->assertDatabaseMissing('quiz_results', [
            'quiz_id' => $quiz->id,
            'user_id' => $legacyStudent->id,
        ]);
        $this->assertFalse(Schema::hasColumn('quiz_results', 'student_id'));
    }

    private function createLegacyTable(bool $includeUserId): void
    {
        Schema::dropIfExists('quiz_result_duplicate_archives');
        Schema::drop('quiz_results');

        Schema::create('quiz_results', function (Blueprint $table) use ($includeUserId) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            if ($includeUserId) {
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            }

            $table->integer('score')->default(0);
            $table->integer('participation_marks')->default(0);
            $table->integer('total_score')->default(0);
            $table->timestamps();
        });
    }

    private function legacyResult(int $quizId, int $studentId, int $score): array
    {
        return [
            'quiz_id' => $quizId,
            'student_id' => $studentId,
            'score' => $score,
            'participation_marks' => 2,
            'total_score' => $score + 2,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function runCompatibilityMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_07_17_180000_canonicalize_quiz_result_user_ownership.php'
        );

        $migration->up();
    }

    private function createQuiz(User $creator): Quiz
    {
        $category = QuizCategory::create([
            'category_name' => 'Migration Compatibility '.uniqid(),
            'created_by' => $creator->id,
        ]);

        return Quiz::create([
            'category_id' => $category->id,
            'title' => 'Legacy Result Quiz '.uniqid(),
            'description' => 'Compatibility migration test',
            'duration' => 10,
            'participation_marks' => 2,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
            'status' => 'Active',
            'created_by' => $creator->id,
        ]);
    }
}
