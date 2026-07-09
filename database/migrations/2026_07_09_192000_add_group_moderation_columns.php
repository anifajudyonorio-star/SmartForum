<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'warnings')) {
                $table->unsignedTinyInteger('warnings')->default(0)->after('Member_Role');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Recreate moderation_logs with group_id + string action (SQLite can't alter enums).
            Schema::rename('moderation_logs', 'moderation_logs_old');

            Schema::create('moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->string('action');
                $table->text('reason')->nullable();
                $table->timestamps();
            });

            $columns = Schema::getColumnListing('moderation_logs_old');

            foreach (DB::table('moderation_logs_old')->get() as $row) {
                DB::table('moderation_logs')->insert([
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'admin_id' => $row->admin_id,
                    'group_id' => in_array('group_id', $columns, true) ? ($row->group_id ?? null) : null,
                    'action' => $row->action,
                    'reason' => $row->reason,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('moderation_logs_old');
        } else {
            if (! Schema::hasColumn('moderation_logs', 'group_id')) {
                Schema::table('moderation_logs', function (Blueprint $table) {
                    $table->unsignedBigInteger('group_id')->nullable()->after('admin_id')->index();
                });
            }

            Schema::table('moderation_logs', function (Blueprint $table) {
                $table->string('action')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'warnings')) {
                $table->dropColumn('warnings');
            }
        });

        if (Schema::hasColumn('moderation_logs', 'group_id')) {
            Schema::table('moderation_logs', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }
};
