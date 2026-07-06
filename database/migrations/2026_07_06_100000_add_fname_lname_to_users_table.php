<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'Fname')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('Fname')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('users', 'Lname')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('Lname')->nullable()->after('Fname');
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            foreach (DB::table('users')->get() as $user) {
                $parts = preg_split('/\s+/', trim($user->name ?? ''), 2);
                DB::table('users')->where('id', $user->id)->update([
                    'Fname' => $parts[0] ?? 'User',
                    'Lname' => $parts[1] ?? '',
                ]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        foreach (DB::table('users')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim(($user->Fname ?? '') . ' ' . ($user->Lname ?? '')),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['Fname', 'Lname']);
        });
    }
};
