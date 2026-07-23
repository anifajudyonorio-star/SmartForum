<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'warnings')) {
                $table->unsignedTinyInteger('warnings')->default(0)->after('role');
            }
            if (! Schema::hasColumn('users', 'is_blacklisted')) {
                $table->boolean('is_blacklisted')->default(false)->after('warnings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_blacklisted')) {
                $table->dropColumn('is_blacklisted');
            }
            if (Schema::hasColumn('users', 'warnings')) {
                $table->dropColumn('warnings');
            }
        });
    }
};
