<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class QuizNotificationService
{
    public function notifyPublishedQuiz(Quiz $quiz): int
    {
        if (! $quiz->isVisibleToStudents()) {
            return 0;
        }

        $students = User::query()
            ->where(function (Builder $query) {
                $query->whereNull('role')->orWhere('role', 'student');
            })
            ->whereHas('groups', function (Builder $query) use ($quiz) {
                $query->where('groups.id', $quiz->group_id)
                    ->where('group_members.Member_Status', GroupMember::STATUS_ACTIVE);
            })
            ->get();

        foreach ($students as $student) {
            $this->notifyStudent($student, $quiz, refreshExisting: false);
        }

        return $students->count();
    }

    public function notifyNewlyActiveMember(User $user, Group $group): int
    {
        if (! $user->isStudent() || ! $group->isActiveMember($user->id)) {
            return 0;
        }

        $quizzes = Quiz::query()
            ->with(['group', 'questions.options'])
            ->where('group_id', $group->id)
            ->whereIn('status', [Quiz::STATUS_SCHEDULED, Quiz::STATUS_ACTIVE])
            ->where('end_time', '>', now())
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->isVisibleToStudents());

        foreach ($quizzes as $quiz) {
            $this->notifyStudent($user, $quiz, refreshExisting: true);
        }

        return $quizzes->count();
    }

    private function notifyStudent(
        User $student,
        Quiz $quiz,
        bool $refreshExisting,
    ): Notification {
        $identity = [
            'user_ID' => $student->id,
            'quiz_id' => $quiz->id,
            'Notification_Type' => 'Quiz',
        ];
        $content = [
            'Notification_Title' => 'New Quiz Available',
            'Message' => 'A new quiz "'.$quiz->title.'" is scheduled for '
                .$quiz->start_time->format('d M Y H:i')
                .' and closes on '
                .$quiz->end_time->format('d M Y H:i').'.',
            'Is_Read' => false,
            'Post_ID' => null,
            'expires_at' => $quiz->end_time,
        ];

        return $refreshExisting
            ? Notification::updateOrCreate($identity, $content)
            : Notification::firstOrCreate($identity, $content);
    }
}
