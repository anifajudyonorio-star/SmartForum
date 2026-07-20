<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['take', 'viewAvailable', 'viewProgress'], true)) {
            return null;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isLecturer();
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz);
    }

    public function create(User $user, ?Group $group = null): bool
    {
        if (! $user->isLecturer()) {
            return false;
        }

        return $group === null || $user->canTeachGroup($group);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz);
    }

    public function publish(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz);
    }

    public function viewReports(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz);
    }

    public function viewAvailable(User $user): bool
    {
        return $user->isStudent();
    }

    public function viewProgress(User $user): bool
    {
        return $user->isStudent();
    }

    public function take(User $user, Quiz $quiz): bool
    {
        return $user->isStudent()
            && $quiz->group_id !== null
            && $quiz->group !== null
            && $quiz->group->isActive()
            && $quiz->group->isActiveMember($user->id)
            && $quiz->category_id !== null
            && $user->isEnrolledInCategory((int) $quiz->category_id);
    }

    public function viewPublicReport(User $user, Quiz $quiz): bool
    {
        return $this->canManage($user, $quiz)
            || ($this->take($user, $quiz)
                && $quiz->isPublished()
                && $quiz->lifecycleStatus() === Quiz::STATUS_CLOSED);
    }

    private function canManage(User $user, Quiz $quiz): bool
    {
        if (! $user->isLecturer()) {
            return false;
        }

        if ($quiz->group_id === null) {
            return (int) $quiz->created_by === (int) $user->id;
        }

        return $quiz->group !== null && $user->canTeachGroup($quiz->group);
    }
}
