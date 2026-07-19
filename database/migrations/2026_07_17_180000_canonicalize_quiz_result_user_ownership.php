<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'quiz_results_quiz_id_user_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('quiz_results')) {
            return;
        }

        if (! Schema::hasColumn('quiz_results', 'user_id')) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('quiz_id');
            });
        }

        // Existing user_id values win when both ownership columns are present.
        // student_id is only used to fill ownership that is currently unknown.
        if (Schema::hasColumn('quiz_results', 'student_id')) {
            DB::table('quiz_results')
                ->whereNull('user_id')
                ->whereNotNull('student_id')
                ->update(['user_id' => DB::raw('student_id')]);
        }

        $this->archiveAndRemoveDuplicates();

        if (Schema::hasColumn('quiz_results', 'student_id')) {
            $this->dropLegacyStudentColumn();
        }

        $hasUnknownOwner = DB::table('quiz_results')->whereNull('user_id')->exists();
        $hasOrphanedOwner = DB::table('quiz_results')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'quiz_results.user_id');
            })
            ->exists();

        // Legacy rows are never discarded merely because their owner is missing.
        // When every owner is valid, enforce the same non-null FK as a fresh schema.
        if (! $hasUnknownOwner && ! $hasOrphanedOwner) {
            if ($this->columnIsNullable('user_id')) {
                Schema::table('quiz_results', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable(false)->change();
                });
            }

            if ($this->foreignKeyFor('user_id') === null) {
                Schema::table('quiz_results', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }
        }

        if (! $this->hasUniqueOwnershipIndex()) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->unique(['quiz_id', 'user_id'], self::UNIQUE_INDEX);
            });
        }
    }

    /**
     * Preserve the lowest-id result as canonical. Every removed duplicate is
     * serialized into quiz_result_duplicate_archives before deletion.
     */
    private function archiveAndRemoveDuplicates(): void
    {
        if (! Schema::hasTable('quiz_result_duplicate_archives')) {
            Schema::create('quiz_result_duplicate_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_result_id')->unique();
                $table->unsignedBigInteger('quiz_id');
                $table->unsignedBigInteger('user_id');
                $table->text('payload');
                $table->timestamp('archived_at');
            });
        }

        $duplicates = DB::table('quiz_results')
            ->select('quiz_id', 'user_id', DB::raw('MIN(id) as canonical_id'))
            ->whereNotNull('user_id')
            ->groupBy('quiz_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $redundantResults = DB::table('quiz_results')
                ->where('quiz_id', $duplicate->quiz_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '<>', $duplicate->canonical_id)
                ->orderBy('id')
                ->get();

            foreach ($redundantResults as $result) {
                DB::table('quiz_result_duplicate_archives')->insertOrIgnore([
                    'original_result_id' => $result->id,
                    'quiz_id' => $duplicate->quiz_id,
                    'user_id' => $duplicate->user_id,
                    'payload' => json_encode((array) $result, JSON_THROW_ON_ERROR),
                    'archived_at' => now(),
                ]);
            }

            DB::table('quiz_results')
                ->whereIn('id', $redundantResults->pluck('id'))
                ->delete();
        }
    }

    private function columnIsNullable(string $column): bool
    {
        foreach (Schema::getColumns('quiz_results') as $definition) {
            if ($definition['name'] === $column) {
                return (bool) $definition['nullable'];
            }
        }

        return true;
    }

    private function dropLegacyStudentColumn(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $foreign = $this->foreignKeyFor('student_id');

            if ($foreign !== null) {
                Schema::table('quiz_results', function (Blueprint $table) use ($foreign) {
                    $table->dropForeign($foreign);
                });
            }

            Schema::table('quiz_results', function (Blueprint $table) {
                $table->dropColumn('student_id');
            });

            return;
        }

        // SQLite cannot natively drop a column that participates in a foreign
        // key. Rebuild the table from its own metadata so every other column,
        // default, foreign key, and user-created index is retained.
        $columns = collect(DB::select("PRAGMA table_info('quiz_results')"))
            ->reject(fn ($column) => $column->name === 'student_id')
            ->values();
        $foreignKeys = collect(Schema::getForeignKeys('quiz_results'))
            ->reject(fn ($foreign) => $foreign['columns'] === ['student_id'])
            ->values();
        $indexes = collect(Schema::getIndexes('quiz_results'))
            ->reject(fn ($index) => $index['primary'])
            ->reject(fn ($index) => in_array('student_id', $index['columns'], true))
            ->values();

        $definitions = $columns->map(function ($column) {
            $name = $this->quoteSqliteIdentifier($column->name);

            if ((int) $column->pk === 1) {
                return "{$name} INTEGER PRIMARY KEY AUTOINCREMENT";
            }

            $definition = trim("{$name} {$column->type}");

            if ((int) $column->notnull === 1) {
                $definition .= ' NOT NULL';
            }

            if ($column->dflt_value !== null) {
                $definition .= " DEFAULT {$column->dflt_value}";
            }

            return $definition;
        });

        foreach ($foreignKeys as $foreign) {
            $localColumns = collect($foreign['columns'])
                ->map(fn ($column) => $this->quoteSqliteIdentifier($column))
                ->implode(', ');
            $foreignColumns = collect($foreign['foreign_columns'])
                ->map(fn ($column) => $this->quoteSqliteIdentifier($column))
                ->implode(', ');
            $definition = "FOREIGN KEY ({$localColumns}) REFERENCES "
                .$this->quoteSqliteIdentifier($foreign['foreign_table'])
                ." ({$foreignColumns})";

            if (($foreign['on_update'] ?? 'no action') !== 'no action') {
                $definition .= ' ON UPDATE '.strtoupper($foreign['on_update']);
            }

            if (($foreign['on_delete'] ?? 'no action') !== 'no action') {
                $definition .= ' ON DELETE '.strtoupper($foreign['on_delete']);
            }

            $definitions->push($definition);
        }

        $columnList = $columns
            ->map(fn ($column) => $this->quoteSqliteIdentifier($column->name))
            ->implode(', ');

        DB::statement('ALTER TABLE "quiz_results" RENAME TO "_quiz_results_before_user_ownership"');
        DB::statement('CREATE TABLE "quiz_results" ('.$definitions->implode(', ').')');
        DB::statement(
            'INSERT INTO "quiz_results" ('.$columnList.') SELECT '.$columnList
            .' FROM "_quiz_results_before_user_ownership"'
        );
        DB::statement('DROP TABLE "_quiz_results_before_user_ownership"');

        foreach ($indexes as $index) {
            Schema::table('quiz_results', function (Blueprint $table) use ($index) {
                if ($index['unique']) {
                    $table->unique($index['columns'], $index['name']);
                } else {
                    $table->index($index['columns'], $index['name']);
                }
            });
        }
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function foreignKeyFor(string $column): ?string
    {
        foreach (Schema::getForeignKeys('quiz_results') as $foreign) {
            if ($foreign['columns'] === [$column]) {
                return $foreign['name'];
            }
        }

        return null;
    }

    private function hasUniqueOwnershipIndex(): bool
    {
        foreach (Schema::getIndexes('quiz_results') as $index) {
            if ($index['unique'] && $index['columns'] === ['quiz_id', 'user_id']) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (Schema::hasTable('quiz_results') && $this->hasIndexNamed(self::UNIQUE_INDEX)) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        // Ownership conversion is intentionally not reversed: recreating
        // student_id would make user_id ambiguous and could orphan new rows.
        Schema::dropIfExists('quiz_result_duplicate_archives');
    }

    private function hasIndexNamed(string $name): bool
    {
        foreach (Schema::getIndexes('quiz_results') as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
