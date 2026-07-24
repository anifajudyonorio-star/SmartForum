<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_participation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->unique()->constrained('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('topic_points')->default(5);
            $table->unsignedSmallInteger('post_points')->default(3);
            $table->unsignedSmallInteger('reply_points')->default(2);
            $table->unsignedSmallInteger('gold_min')->default(50);
            $table->unsignedSmallInteger('silver_min')->default(30);
            $table->unsignedSmallInteger('bronze_min')->default(15);
            $table->unsignedSmallInteger('manual_marks_max')->default(20);
            $table->timestamps();
        });

        Schema::create('participation_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('manual_marks')->default(0);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_grades');
        Schema::dropIfExists('group_participation_settings');
    }
};
