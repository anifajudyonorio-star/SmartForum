<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'parent_post_id')) {
                $table->unsignedBigInteger('parent_post_id')->nullable()->after('Post_ID')->index();
            }
            if (! Schema::hasColumn('notifications', 'reply_count')) {
                $table->unsignedInteger('reply_count')->default(1)->after('parent_post_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'reply_count')) {
                $table->dropColumn('reply_count');
            }
            if (Schema::hasColumn('notifications', 'parent_post_id')) {
                $table->dropColumn('parent_post_id');
            }
        });
    }
};
