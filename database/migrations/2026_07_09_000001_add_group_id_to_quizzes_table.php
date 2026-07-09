<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'group_id')) {
                $table->foreignId('group_id')
                      ->nullable()
                      ->after('category_id')
                      ->constrained('groups')
                      ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'group_id')) {
                $table->dropConstrainedForeignId('group_id');
            }
        });
    }
};
