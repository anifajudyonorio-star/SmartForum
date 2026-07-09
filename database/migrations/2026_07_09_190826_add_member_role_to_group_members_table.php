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
            if (! Schema::hasColumn('group_members', 'Member_Role')) {
                $table->string('Member_Role')->default('member')->after('Member_Status');
            }
        });

        // Existing members default to "member"; group creators become admins.
        if (Schema::hasColumn('group_members', 'Member_Role')) {
            DB::table('group_members')->whereNull('Member_Role')->update(['Member_Role' => 'member']);

            $creators = DB::table('groups')
                ->whereNotNull('Created_By')
                ->get(['id', 'Created_By']);

            foreach ($creators as $group) {
                $exists = DB::table('group_members')
                    ->where('Group_ID', $group->id)
                    ->where('User_ID', $group->Created_By)
                    ->exists();

                if ($exists) {
                    DB::table('group_members')
                        ->where('Group_ID', $group->id)
                        ->where('User_ID', $group->Created_By)
                        ->update(['Member_Role' => 'admin']);
                } else {
                    DB::table('group_members')->insert([
                        'User_ID' => $group->Created_By,
                        'Group_ID' => $group->id,
                        'Member_Status' => 'Active',
                        'Member_Role' => 'admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'Member_Role')) {
                $table->dropColumn('Member_Role');
            }
        });
    }
};
