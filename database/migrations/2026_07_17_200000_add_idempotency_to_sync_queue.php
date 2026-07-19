<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sync_queue')) {
            return;
        }

        Schema::table('sync_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_queue', 'action_uuid')) {
                $table->uuid('action_uuid')->nullable()->after('id');
            }
            if (! Schema::hasColumn('sync_queue', 'sync_status')) {
                $table->string('sync_status', 20)->default('pending')->after('is_synced');
            }
            if (! Schema::hasColumn('sync_queue', 'last_error')) {
                $table->text('last_error')->nullable()->after('sync_status');
            }
        });

        if (Schema::hasColumn('sync_queue', 'sync_status')) {
            DB::table('sync_queue')
                ->where('is_synced', true)
                ->update(['sync_status' => 'succeeded']);
        }

        if (! $this->hasUniqueActionIndex()) {
            Schema::table('sync_queue', function (Blueprint $table) {
                $table->unique(['user_id', 'action_uuid'], 'sync_queue_user_action_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sync_queue')) {
            return;
        }

        Schema::table('sync_queue', function (Blueprint $table) {
            if ($this->hasIndexNamed('sync_queue_user_action_uuid_unique')) {
                $table->dropUnique('sync_queue_user_action_uuid_unique');
            }

            $columns = collect(['action_uuid', 'sync_status', 'last_error'])
                ->filter(fn ($column) => Schema::hasColumn('sync_queue', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasUniqueActionIndex(): bool
    {
        foreach (Schema::getIndexes('sync_queue') as $index) {
            if ($index['unique'] && $index['columns'] === ['user_id', 'action_uuid']) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexNamed(string $name): bool
    {
        return collect(Schema::getIndexes('sync_queue'))->contains(
            fn ($index) => $index['name'] === $name,
        );
    }
};
