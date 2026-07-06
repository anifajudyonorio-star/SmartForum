<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'Group_Name')) {
                $table->string('Group_Name')->nullable();
            }
            if (! Schema::hasColumn('groups', 'Description')) {
                $table->text('Description')->nullable();
            }
            if (! Schema::hasColumn('groups', 'Created_By')) {
                $table->unsignedBigInteger('Created_By')->nullable()->index();
            }
            if (! Schema::hasColumn('groups', 'Status')) {
                $table->string('Status')->default('Active');
            }
        });

        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'User_ID')) {
                $table->unsignedBigInteger('User_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('group_members', 'Group_ID')) {
                $table->unsignedBigInteger('Group_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('group_members', 'Member_Status')) {
                $table->string('Member_Status')->default('Active');
            }
        });

        Schema::table('topics', function (Blueprint $table) {
            if (! Schema::hasColumn('topics', 'Title')) {
                $table->string('Title')->nullable();
            }
            if (! Schema::hasColumn('topics', 'Topic_Description')) {
                $table->text('Topic_Description')->nullable();
            }
            if (! Schema::hasColumn('topics', 'Group_ID')) {
                $table->unsignedBigInteger('Group_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('topics', 'Created_By')) {
                $table->unsignedBigInteger('Created_By')->nullable()->index();
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'Topic_ID')) {
                $table->unsignedBigInteger('Topic_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('posts', 'Parent_Post_ID')) {
                $table->unsignedBigInteger('Parent_Post_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('posts', 'Created_By')) {
                $table->unsignedBigInteger('Created_By')->nullable()->index();
            }
            if (! Schema::hasColumn('posts', 'Post_Content')) {
                $table->text('Post_Content')->nullable();
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'user_ID')) {
                $table->unsignedBigInteger('user_ID')->nullable()->index();
            }
            if (! Schema::hasColumn('notifications', 'Notification_Type')) {
                $table->string('Notification_Type')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'Notification_Title')) {
                $table->string('Notification_Title')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'Message')) {
                $table->text('Message')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'Is_Read')) {
                $table->boolean('Is_Read')->default(false);
            }
            if (! Schema::hasColumn('notifications', 'Post_ID')) {
                $table->unsignedBigInteger('Post_ID')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'user_ID',
                'Notification_Type',
                'Notification_Title',
                'Message',
                'Is_Read',
                'Post_ID',
            ]);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'Topic_ID',
                'Parent_Post_ID',
                'Created_By',
                'Post_Content',
            ]);
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn([
                'Title',
                'Topic_Description',
                'Group_ID',
                'Created_By',
            ]);
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn([
                'User_ID',
                'Group_ID',
                'Member_Status',
            ]);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn([
                'Group_Name',
                'Description',
                'Created_By',
                'Status',
            ]);
        });
    }
};
