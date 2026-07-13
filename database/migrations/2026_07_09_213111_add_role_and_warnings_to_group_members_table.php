<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'Member_Role')) {
                $table->string('Member_Role')->default('member')->after('Member_Status');
            }
            if (!Schema::hasColumn('group_members', 'warnings')) {
                $table->unsignedInteger('warnings')->default(0)->after('Member_Role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn(['Member_Role', 'warnings']);
        });
    }
};
