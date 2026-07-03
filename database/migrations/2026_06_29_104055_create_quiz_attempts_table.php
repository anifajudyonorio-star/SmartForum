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
    Schema::create('quiz_attempts', function (Blueprint $table) {

        $table->id();

        $table->foreignId('quiz_id')
              ->constrained('quizzes')
              ->cascadeOnDelete();

        $table->foreignId('user_id')
              ->constrained('users')
              ->cascadeOnDelete();

        $table->timestamp('started_at');

        $table->timestamp('submitted_at')->nullable();

        $table->decimal('score', 5, 2)->default(0);

        $table->enum('status', [
            'In Progress',
            'Submitted',
            'Auto Submitted'
        ])->default('In Progress');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
