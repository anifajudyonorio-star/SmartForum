<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('post_reports') && ! Schema::hasTable('reports')) {
            Schema::rename('post_reports', 'reports');

            return;
        }

        if (Schema::hasTable('post_reports') && Schema::hasTable('reports')) {
            Schema::drop('reports');
            Schema::rename('post_reports', 'reports');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reports') && ! Schema::hasTable('post_reports')) {
            Schema::rename('reports', 'post_reports');
        }
    }
};
