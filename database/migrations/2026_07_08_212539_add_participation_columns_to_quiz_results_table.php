<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quiz_results', 'participation_marks')) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->integer('participation_marks')->default(0);
            });
        }

        if (! Schema::hasColumn('quiz_results', 'total_score')) {
            Schema::table('quiz_results', function (Blueprint $table) {
                $table->integer('total_score')->default(0);
            });
        }
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
