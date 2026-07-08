<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->integer('participation_marks')->default(0)->after('score');
            $table->integer('total_score')->default(0)->after('participation_marks');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn([
                'participation_marks',
                'total_score',
            ]);
        });
    }
};