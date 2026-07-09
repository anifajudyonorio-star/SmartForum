<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'quiz_id')) {
                $table->unsignedBigInteger('quiz_id')->nullable()->index();
            }
            if (! Schema::hasColumn('notifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'quiz_id')) {
                $table->dropColumn('quiz_id');
            }
            if (Schema::hasColumn('notifications', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
