<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait FormatsUserPermissions
{
    /**
     * @return array<string, mixed>
     */
    protected function userPermissions(User $user): array
    {
        $administeredCount = $user->administeredGroups()->count();

        return [
            'is_system_admin' => $user->isAdmin(),
            'is_lecturer' => $user->isLecturer(),
            'can_view_statistics' => $user->canViewStatistics(),
            'can_view_participation' => $user->canViewParticipation(),
            'administers_groups' => $administeredCount > 0,
            'administered_groups_count' => $administeredCount,
        ];
    }
}
