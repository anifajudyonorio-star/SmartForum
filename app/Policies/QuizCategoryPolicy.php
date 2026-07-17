<?php

namespace App\Policies;

use App\Models\QuizCategory;
use App\Models\User;

class QuizCategoryPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isLecturer();
    }

    public function create(User $user): bool
    {
        return $user->isLecturer();
    }

    public function assign(User $user, QuizCategory $category): bool
    {
        return $user->isLecturer()
            && (int) $category->created_by === (int) $user->id;
    }

    public function update(User $user, QuizCategory $category): bool
    {
        return $this->assign($user, $category);
    }

    public function delete(User $user, QuizCategory $category): bool
    {
        return $this->update($user, $category)
            && ! $category->quizzes()->exists();
    }
}
