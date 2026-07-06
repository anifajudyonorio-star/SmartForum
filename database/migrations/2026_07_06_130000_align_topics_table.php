<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('topics', 'content') ? 'content' : null,
                Schema::hasColumn('topics', 'predicted_category') ? 'predicted_category' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }

            if (! Schema::hasColumn('topics', 'Title')) {
                $table->string('Title')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            if (! Schema::hasColumn('topics', 'content')) {
                $table->text('content')->nullable();
            }
            if (! Schema::hasColumn('topics', 'predicted_category')) {
                $table->string('predicted_category')->nullable();
            }
        });
    }
};
