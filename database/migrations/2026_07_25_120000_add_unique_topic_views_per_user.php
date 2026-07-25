<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keeperIds = DB::table('topic_views')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('user_id', 'topic_id')
            ->pluck('id');

        if ($keeperIds->isNotEmpty()) {
            DB::table('topic_views')
                ->whereNotIn('id', $keeperIds)
                ->delete();
        }

        Schema::table('topic_views', function (Blueprint $table) {
            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::table('topic_views', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'topic_id']);
        });
    }
};
