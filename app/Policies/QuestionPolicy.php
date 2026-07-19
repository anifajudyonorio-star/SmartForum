<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;

class QuestionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isLecturer();
    }

    public function create(User $user, Quiz $quiz): bool
    {
        return $user->can('update', $quiz);
    }

    public function update(User $user, Question $question): bool
    {
        return $user->can('update', $question->quiz);
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->can('update', $question->quiz);
    }
}
