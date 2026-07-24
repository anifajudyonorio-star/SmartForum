<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'inactivity_monitoring_enabled')) {
                $table->boolean('inactivity_monitoring_enabled')->default(true)->after('Status');
            }
            if (! Schema::hasColumn('groups', 'inactivity_threshold_days')) {
                $table->unsignedSmallInteger('inactivity_threshold_days')->default(14)->after('inactivity_monitoring_enabled');
            }
            if (! Schema::hasColumn('groups', 'inactivity_grace_days')) {
                $table->unsignedSmallInteger('inactivity_grace_days')->default(7)->after('inactivity_threshold_days');
            }
            if (! Schema::hasColumn('groups', 'inactivity_blacklist_days')) {
                $table->unsignedSmallInteger('inactivity_blacklist_days')->default(30)->after('inactivity_grace_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            foreach ([
                'inactivity_blacklist_days',
                'inactivity_grace_days',
                'inactivity_threshold_days',
                'inactivity_monitoring_enabled',
            ] as $column) {
                if (Schema::hasColumn('groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
