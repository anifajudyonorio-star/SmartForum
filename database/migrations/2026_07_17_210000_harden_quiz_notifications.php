<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')
            || ! Schema::hasTable('quizzes')
            || ! Schema::hasColumn('notifications', 'quiz_id')) {
            return;
        }

        DB::table('notifications')
            ->whereNotNull('quiz_id')
            ->whereNotIn('quiz_id', DB::table('quizzes')->select('id'))
            ->update(['quiz_id' => null]);

        $seen = [];
        $duplicateIds = [];

        foreach (DB::table('notifications')
            ->whereNotNull('quiz_id')
            ->orderBy('id')
            ->get(['id', 'user_ID', 'quiz_id', 'Notification_Type']) as $notification) {
            $key = implode(':', [
                $notification->user_ID,
                $notification->quiz_id,
                $notification->Notification_Type,
            ]);

            if (isset($seen[$key])) {
                $duplicateIds[] = $notification->id;
            } else {
                $seen[$key] = true;
            }
        }

        if ($duplicateIds !== []) {
            DB::table('notifications')->whereIn('id', $duplicateIds)->delete();
        }

        $quizForeign = $this->quizForeign();

        if ($quizForeign !== null
            && strtolower((string) ($quizForeign['on_delete'] ?? '')) !== 'set null') {
            Schema::table('notifications', function (Blueprint $table) use ($quizForeign) {
                $table->dropForeign($quizForeign['name']);
            });
            $quizForeign = null;
        }

        if ($quizForeign === null) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->foreign('quiz_id', 'notifications_quiz_id_foreign')
                    ->references('id')
                    ->on('quizzes')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasUniqueQuizNotificationIndex()) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unique(
                    ['user_ID', 'quiz_id', 'Notification_Type'],
                    'notifications_user_quiz_type_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if ($this->hasForeignNamed('notifications_quiz_id_foreign')) {
                $table->dropForeign('notifications_quiz_id_foreign');
            }
            if ($this->hasIndexNamed('notifications_user_quiz_type_unique')) {
                $table->dropUnique('notifications_user_quiz_type_unique');
            }
        });
    }

    private function quizForeign(): ?array
    {
        foreach (Schema::getForeignKeys('notifications') as $foreign) {
            if ($foreign['columns'] === ['quiz_id']) {
                return $foreign;
            }
        }

        return null;
    }

    private function hasUniqueQuizNotificationIndex(): bool
    {
        foreach (Schema::getIndexes('notifications') as $index) {
            if ($index['unique']
                && $index['columns'] === ['user_ID', 'quiz_id', 'Notification_Type']) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignNamed(string $name): bool
    {
        return collect(Schema::getForeignKeys('notifications'))->contains(
            fn ($foreign) => $foreign['name'] === $name,
        );
    }

    private function hasIndexNamed(string $name): bool
    {
        return collect(Schema::getIndexes('notifications'))->contains(
            fn ($index) => $index['name'] === $name,
        );
    }
};
