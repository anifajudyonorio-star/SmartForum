<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupJoinService
{
    public static function exploreGroups(User $user): Collection
    {
        if ($user->isAdmin()) {
            return collect();
        }

        $viewableIds = $user->viewableGroupIds();

        return Group::query()
            ->with('user')
            ->withCount('topics')
            ->withCount(['memberships as members_count' => function ($query) {
                $query->whereIn('Member_Status', [
                    GroupMember::STATUS_ACTIVE,
                    GroupMember::STATUS_SUSPENDED,
                ]);
            }])
            ->when($viewableIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $viewableIds))
            ->latest()
            ->get()
            ->map(function (Group $group) use ($user) {
                $group->join_status = self::joinStatusFor($user, $group);

                return $group;
            });
    }

    public static function joinStatusFor(User $user, Group $group): string
    {
        $status = $group->memberStatus($user->id);

        if ($status === GroupMember::STATUS_PENDING) {
            return 'pending';
        }

        if ($status === GroupMember::STATUS_BLOCKED) {
            return 'blocked';
        }

        return 'none';
    }

    public static function requestJoin(User $user, Group $group, bool $acceptedRules = false): void
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'group' => ['System admins oversee all groups and cannot request to join.'],
            ]);
        }

        if (! $user->canRequestJoinGroup($group)) {
            throw ValidationException::withMessages([
                'group' => ['You cannot request to join this group.'],
            ]);
        }

        if (filled($group->join_rules) && ! $acceptedRules) {
            throw ValidationException::withMessages([
                'accepted_rules' => ['You must read and accept the group rules before requesting to join.'],
            ]);
        }

        DB::transaction(function () use ($user, $group) {
            $group->members()->attach($user->id, [
                'Member_Status' => GroupMember::STATUS_PENDING,
                'Member_Role' => GroupMember::ROLE_MEMBER,
                'warnings' => 0,
                'rules_accepted_at' => filled($group->join_rules) ? now() : null,
            ]);
        });

        self::notifyAdminsOfJoinRequest($user, $group);
    }

    public static function approveJoinRequest(Group $group, User $requester, User $approver): void
    {
        abort_unless($approver->canManageGroup($group), 403);
        abort_unless(
            $group->memberStatus($requester->id) === GroupMember::STATUS_PENDING,
            422,
            'This user does not have a pending join request.'
        );

        $group->members()->updateExistingPivot($requester->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'Member_Role' => GroupMember::ROLE_MEMBER,
        ]);

        Notification::create([
            'user_ID' => $requester->id,
            'Notification_Type' => 'GroupJoinApproved',
            'Notification_Title' => $group->Group_Name,
            'Message' => $approver->name.' approved your request to join "'.$group->Group_Name.'".',
            'Is_Read' => false,
            'group_id' => $group->id,
        ]);
    }

    public static function rejectJoinRequest(Group $group, User $requester, User $rejecter): void
    {
        abort_unless($rejecter->canManageGroup($group), 403);
        abort_unless(
            $group->memberStatus($requester->id) === GroupMember::STATUS_PENDING,
            422,
            'This user does not have a pending join request.'
        );

        $group->members()->detach($requester->id);

        Notification::create([
            'user_ID' => $requester->id,
            'Notification_Type' => 'GroupJoinRejected',
            'Notification_Title' => $group->Group_Name,
            'Message' => 'Your request to join "'.$group->Group_Name.'" was declined.',
            'Is_Read' => false,
            'group_id' => $group->id,
        ]);
    }

    public static function pendingRequestsFor(Group $group): Collection
    {
        return $group->members()
            ->wherePivot('Member_Status', GroupMember::STATUS_PENDING)
            ->orderBy('Fname')
            ->orderBy('Lname')
            ->get();
    }

    private static function notifyAdminsOfJoinRequest(User $requester, Group $group): void
    {
        $admins = $group->members()
            ->wherePivot('Member_Role', GroupMember::ROLE_ADMIN)
            ->wherePivot('Member_Status', GroupMember::STATUS_ACTIVE)
            ->get();

        foreach ($admins as $admin) {
            try {
                Notification::create([
                    'user_ID' => $admin->id,
                    'Notification_Type' => 'GroupJoinRequest',
                    'Notification_Title' => $requester->name,
                    'Message' => $requester->name.' requested to join "'.$group->Group_Name.'".',
                    'Is_Read' => false,
                    'group_id' => $group->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
