<?php

namespace App\Policies;

use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use App\Models\User;

class QuizAnnouncementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['viewStudentFeed'], true)) {
            return null;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isLecturer();
    }

    public function create(User $user, ?QuizCategory $category = null): bool
    {
        if (! $user->isLecturer()) {
            return false;
        }

        return $category === null || (int) $category->created_by === (int) $user->id;
    }

    public function delete(User $user, QuizAnnouncement $announcement): bool
    {
        if (! $user->isLecturer()) {
            return false;
        }

        $category = $announcement->category;

        return $category !== null
            && (int) $category->created_by === (int) $user->id;
    }

    public function viewStudentFeed(User $user): bool
    {
        return $user->isStudent();
    }
}
