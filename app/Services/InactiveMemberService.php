<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ModerationLog;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InactiveMemberService
{
    public function recordActivity(Group $group, User $user): void
    {
        $membership = $group->membership($user->id);

        if (! $membership || ! $membership->isActive()) {
            return;
        }

        $updates = [
            'last_activity_at' => now(),
            'inactive_warning_sent_at' => null,
        ];

        if ((int) ($membership->warnings ?? 0) > 0) {
            $updates['warnings'] = 0;
        }

        $group->members()->updateExistingPivot($user->id, $updates);
    }

    public function processAll(): array
    {
        $released = $this->releaseExpiredSuspensions();
        $warned = 0;
        $suspended = 0;

        Group::query()
            ->where('Status', 'Active')
            ->where('inactivity_monitoring_enabled', true)
            ->each(function (Group $group) use (&$warned, &$suspended) {
                [$groupWarned, $groupSuspended] = $this->processGroup($group);
                $warned += $groupWarned;
                $suspended += $groupSuspended;
            });

        return [
            'released' => $released,
            'warned' => $warned,
            'suspended' => $suspended,
        ];
    }

    public function releaseExpiredSuspensions(): int
    {
        $memberships = GroupMember::query()
            ->where('Member_Status', GroupMember::STATUS_SUSPENDED)
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->with(['user', 'group'])
            ->get();

        foreach ($memberships as $membership) {
            $this->reinstateMembership($membership, 'Automatic reinstatement after inactivity suspension expired.');
        }

        return $memberships->count();
    }

    public function releaseIfExpired(Group $group, User $user): void
    {
        $membership = $group->membership($user->id);

        if (! $membership
            || $membership->Member_Status !== GroupMember::STATUS_SUSPENDED
            || ! $membership->suspended_until
            || $membership->suspended_until->isFuture()) {
            return;
        }

        $this->reinstateMembership($membership, 'Automatic reinstatement after inactivity suspension expired.');
    }

    /**
     * @return array{0: int, 1: int} warned count, suspended count
     */
    private function processGroup(Group $group): array
    {
        $warned = 0;
        $suspended = 0;

        $memberships = GroupMember::query()
            ->where('Group_ID', $group->id)
            ->where('Member_Status', GroupMember::STATUS_ACTIVE)
            ->with('user')
            ->get();

        foreach ($memberships as $membership) {
            if (! $membership->user) {
                continue;
            }

            if ($group->isGroupAdmin($membership->User_ID)) {
                continue;
            }

            $result = $this->evaluateMembership($group, $membership);

            if ($result === 'warned') {
                $warned++;
            } elseif ($result === 'suspended') {
                $suspended++;
            }
        }

        return [$warned, $suspended];
    }

    private function evaluateMembership(Group $group, GroupMember $membership): ?string
    {
        $thresholdDays = max(1, (int) $group->inactivity_threshold_days);
        $graceDays = max(1, (int) $group->inactivity_grace_days);
        $blacklistDays = max(1, (int) $group->inactivity_blacklist_days);

        $lastActivity = $this->resolveLastActivity($group, $membership);
        $daysInactive = $lastActivity->diffInDays(now());

        if ($daysInactive < $thresholdDays) {
            return null;
        }

        $warnings = (int) ($membership->warnings ?? 0);
        $warningSentAt = $membership->inactive_warning_sent_at
            ? Carbon::parse($membership->inactive_warning_sent_at)
            : null;

        if ($warnings === 0) {
            $this->issueWarning($group, $membership, 1, $daysInactive);

            return 'warned';
        }

        if ($warnings === 1) {
            if (! $warningSentAt || $warningSentAt->diffInDays(now()) < $graceDays) {
                return null;
            }

            $this->issueWarning($group, $membership, 2, $daysInactive);

            return 'warned';
        }

        if ($warnings >= 2) {
            if (! $warningSentAt || $warningSentAt->diffInDays(now()) < $graceDays) {
                return null;
            }

            $this->suspendForInactivity($group, $membership, $blacklistDays, $daysInactive);

            return 'suspended';
        }

        return null;
    }

    private function resolveLastActivity(Group $group, GroupMember $membership): Carbon
    {
        if ($membership->last_activity_at) {
            return Carbon::parse($membership->last_activity_at);
        }

        $latestPostAt = Post::query()
            ->where('Created_By', $membership->User_ID)
            ->whereIn('Topic_ID', Topic::query()->where('Group_ID', $group->id)->select('id'))
            ->max('created_at');

        if ($latestPostAt) {
            return Carbon::parse($latestPostAt);
        }

        return Carbon::parse($membership->created_at ?? now());
    }

    private function issueWarning(Group $group, GroupMember $membership, int $warningNumber, int $daysInactive): void
    {
        DB::transaction(function () use ($group, $membership, $warningNumber, $daysInactive) {
            $group->members()->updateExistingPivot($membership->User_ID, [
                'warnings' => $warningNumber,
                'inactive_warning_sent_at' => now(),
            ]);

            ModerationLog::create([
                'user_id' => $membership->User_ID,
                'admin_id' => $group->Created_By,
                'group_id' => $group->id,
                'action' => 'inactive_warning',
                'reason' => "Automatic inactivity warning {$warningNumber}/2 after {$daysInactive} days without posting.",
            ]);

            Notification::create([
                'user_ID' => $membership->User_ID,
                'Notification_Type' => 'warning',
                'Notification_Title' => "⚠️ Inactivity warning {$warningNumber}/2 in {$group->Group_Name}",
                'Message' => $warningNumber >= 2
                    ? 'You have been inactive in this group. This is your final warning — post within '
                        .$group->inactivity_grace_days
                        .' days or you will be temporarily suspended.'
                    : 'You have not posted in this group for '
                        .$group->inactivity_threshold_days
                        .' days. Please participate within '
                        .$group->inactivity_grace_days
                        .' days to avoid another warning.',
                'Is_Read' => false,
            ]);
        });
    }

    private function suspendForInactivity(Group $group, GroupMember $membership, int $blacklistDays, int $daysInactive): void
    {
        $until = now()->addDays($blacklistDays);

        DB::transaction(function () use ($group, $membership, $blacklistDays, $daysInactive, $until) {
            $group->members()->updateExistingPivot($membership->User_ID, [
                'Member_Status' => GroupMember::STATUS_SUSPENDED,
                'suspended_until' => $until,
                'inactive_warning_sent_at' => now(),
            ]);

            ModerationLog::create([
                'user_id' => $membership->User_ID,
                'admin_id' => $group->Created_By,
                'group_id' => $group->id,
                'action' => 'inactive_suspend',
                'reason' => "Automatic suspension after inactivity ({$daysInactive} days). Suspended for {$blacklistDays} days.",
            ]);

            Notification::create([
                'user_ID' => $membership->User_ID,
                'Notification_Type' => 'warning',
                'Notification_Title' => '⛔ Temporarily suspended from '.$group->Group_Name,
                'Message' => 'You did not participate after two inactivity warnings and have been suspended for '
                    .$blacklistDays
                    .' days. You can rejoin discussions automatically when the suspension ends.',
                'Is_Read' => false,
            ]);
        });
    }

    private function reinstateMembership(GroupMember $membership, string $reason): void
    {
        $group = $membership->group;
        $user = $membership->user;

        if (! $group || ! $user) {
            return;
        }

        DB::transaction(function () use ($group, $user, $membership, $reason) {
            $group->members()->updateExistingPivot($user->id, [
                'Member_Status' => GroupMember::STATUS_ACTIVE,
                'warnings' => 0,
                'suspended_until' => null,
                'inactive_warning_sent_at' => null,
                'last_activity_at' => now(),
            ]);

            ModerationLog::create([
                'user_id' => $user->id,
                'admin_id' => $group->Created_By,
                'group_id' => $group->id,
                'action' => 'inactive_reinstate',
                'reason' => $reason,
            ]);

            Notification::create([
                'user_ID' => $user->id,
                'Notification_Type' => 'warning',
                'Notification_Title' => '✅ Reinstated in '.$group->Group_Name,
                'Message' => 'Your inactivity suspension has ended and you can participate again.',
                'Is_Read' => false,
            ]);
        });
    }
}
