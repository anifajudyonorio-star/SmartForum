<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'deleted_at')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::dropIfExists('reports');

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'reporter_id']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');

        if (Schema::hasColumn('posts', 'deleted_at')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
