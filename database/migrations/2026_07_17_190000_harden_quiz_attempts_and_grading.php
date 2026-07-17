<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ATTEMPT_UNIQUE = 'quiz_attempts_quiz_id_user_id_unique';

    public function up(): void
    {
        $this->archiveAndRemoveDuplicateAttempts();

        if (! $this->hasIndex('quiz_attempts', ['quiz_id', 'user_id'], unique: true)) {
            Schema::table('quiz_attempts', function (Blueprint $table) {
                $table->unique(['quiz_id', 'user_id'], self::ATTEMPT_UNIQUE);
            });
        }

        if (! Schema::hasTable('quiz_attempt_answers')) {
            Schema::create('quiz_attempt_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')
                    ->constrained('quiz_attempts')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('question_id');
                $table->unsignedBigInteger('selected_option_id')->nullable();
                $table->unsignedBigInteger('correct_option_id')->nullable();
                $table->text('question_text_snapshot');
                $table->string('question_type_snapshot');
                $table->integer('question_marks_snapshot');
                $table->text('selected_option_text_snapshot')->nullable();
                $table->text('correct_option_text_snapshot')->nullable();
                $table->json('options_snapshot')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->integer('awarded_marks')->default(0);
                $table->timestamps();

                $table->unique(['attempt_id', 'question_id']);
            });
        }

        Schema::table('quiz_results', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_results', 'attempt_id')) {
                $table->foreignId('attempt_id')
                    ->nullable()
                    ->after('quiz_id')
                    ->constrained('quiz_attempts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('quiz_results', 'maximum_score')) {
                $table->integer('maximum_score')->nullable()->after('score');
            }

            if (! Schema::hasColumn('quiz_results', 'maximum_total_score')) {
                $table->integer('maximum_total_score')->nullable()->after('total_score');
            }

            if (! Schema::hasColumn('quiz_results', 'grading_snapshot')) {
                $table->json('grading_snapshot')->nullable()->after('maximum_total_score');
            }

            if (! Schema::hasColumn('quiz_results', 'graded_at')) {
                $table->timestamp('graded_at')->nullable()->after('grading_snapshot');
            }
        });

        if (! $this->hasIndex('quiz_results', ['attempt_id'], unique: true)) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->unique('attempt_id', 'quiz_results_attempt_id_unique');
            });
        }
    }

    /**
     * Older web flows could create multiple attempts. Keep the lowest id and
     * archive every redundant attempt, including dead legacy answer rows,
     * before enforcing one attempt per quiz/user.
     */
    private function archiveAndRemoveDuplicateAttempts(): void
    {
        if (! Schema::hasTable('quiz_attempt_duplicate_archives')) {
            Schema::create('quiz_attempt_duplicate_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_attempt_id')->unique();
                $table->unsignedBigInteger('quiz_id');
                $table->unsignedBigInteger('user_id');
                $table->text('payload');
                $table->timestamp('archived_at');
            });
        }

        $duplicates = DB::table('quiz_attempts')
            ->select('quiz_id', 'user_id', DB::raw('MIN(id) as canonical_id'))
            ->groupBy('quiz_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $redundantAttempts = DB::table('quiz_attempts')
                ->where('quiz_id', $duplicate->quiz_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '<>', $duplicate->canonical_id)
                ->orderBy('id')
                ->get();

            foreach ($redundantAttempts as $attempt) {
                $payload = ['attempt' => (array) $attempt];

                if (Schema::hasTable('student_answers')) {
                    $payload['legacy_answers'] = DB::table('student_answers')
                        ->where('attempt_id', $attempt->id)
                        ->get()
                        ->map(fn ($answer) => (array) $answer)
                        ->all();
                }

                DB::table('quiz_attempt_duplicate_archives')->insertOrIgnore([
                    'original_attempt_id' => $attempt->id,
                    'quiz_id' => $duplicate->quiz_id,
                    'user_id' => $duplicate->user_id,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'archived_at' => now(),
                ]);
            }

            DB::table('quiz_attempts')
                ->whereIn('id', $redundantAttempts->pluck('id'))
                ->delete();
        }
    }

    private function hasIndex(string $table, array $columns, bool $unique): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ((bool) $index['unique'] === $unique && $index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (Schema::hasTable('quiz_results')) {
            Schema::table('quiz_results', function (Blueprint $table) {
                if (Schema::hasColumn('quiz_results', 'attempt_id')) {
                    $table->dropUnique('quiz_results_attempt_id_unique');
                    $table->dropConstrainedForeignId('attempt_id');
                }

                $columns = collect([
                    'maximum_score',
                    'maximum_total_score',
                    'grading_snapshot',
                    'graded_at',
                ])->filter(fn ($column) => Schema::hasColumn('quiz_results', $column))->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('quiz_attempt_answers');

        if (Schema::hasTable('quiz_attempts')
            && $this->hasIndex('quiz_attempts', ['quiz_id', 'user_id'], unique: true)) {
            Schema::table('quiz_attempts', function (Blueprint $table) {
                $table->dropUnique(self::ATTEMPT_UNIQUE);
            });
        }

        Schema::dropIfExists('quiz_attempt_duplicate_archives');
    }
};
