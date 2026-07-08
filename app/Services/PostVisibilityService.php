<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

class PostVisibilityService
{
    public static function syncHiddenFrom(Post $post, Topic $topic, array $excludedUserIds, User $author): void
    {
        $memberIds = $topic->group->members()->pluck('users.id');

        $validIds = collect($excludedUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $author->id)
            ->unique()
            ->intersect($memberIds)
            ->values()
            ->all();

        $post->hiddenFromUsers()->sync($validIds);
    }

    public static function groupMembersExcept(Topic $topic, User $currentUser): Collection
    {
        return $topic->group->members()
            ->where('users.id', '!=', $currentUser->id)
            ->orderBy('Fname')
            ->orderBy('Lname')
            ->get(['users.id', 'users.Fname', 'users.Lname', 'users.email']);
    }
}
