<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quiz_results')) {
            Schema::create('quiz_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->integer('score')->default(0);
                $table->integer('participation_marks')->default(0);
                $table->integer('total_score')->default(0);
                $table->timestamps();
            });

            return;
        }

        Schema::table('quiz_results', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_results', 'participation_marks')) {
                $table->integer('participation_marks')->default(0);
            }

            if (! Schema::hasColumn('quiz_results', 'total_score')) {
                $table->integer('total_score')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quiz_results')) {
            return;
        }

        Schema::table('quiz_results', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('quiz_results', 'participation_marks')) {
                $columns[] = 'participation_marks';
            }

            if (Schema::hasColumn('quiz_results', 'total_score')) {
                $columns[] = 'total_score';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
