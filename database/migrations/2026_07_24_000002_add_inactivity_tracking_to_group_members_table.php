<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('warnings');
            }
            if (! Schema::hasColumn('group_members', 'inactive_warning_sent_at')) {
                $table->timestamp('inactive_warning_sent_at')->nullable()->after('last_activity_at');
            }
            if (! Schema::hasColumn('group_members', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->after('inactive_warning_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            foreach (['suspended_until', 'inactive_warning_sent_at', 'last_activity_at'] as $column) {
                if (Schema::hasColumn('group_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
