<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'join_rules')) {
                $table->text('join_rules')->nullable()->after('Description');
            }
        });

        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'rules_accepted_at')) {
                $table->timestamp('rules_accepted_at')->nullable()->after('suspended_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'rules_accepted_at')) {
                $table->dropColumn('rules_accepted_at');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'join_rules')) {
                $table->dropColumn('join_rules');
            }
        });
    }
};
