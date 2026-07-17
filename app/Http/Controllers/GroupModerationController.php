<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ModerationLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\QuizNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupModerationController extends Controller
{
    public function __construct(private readonly QuizNotificationService $quizNotifications) {}

    public function warn(Request $request, Group $group, User $user)
    {
        $this->authorizeModeration($group, $user);

        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $membership = $group->membership($user->id);
        $warnings = min(2, ((int) $membership->warnings) + 1);

        $updates = ['warnings' => $warnings];

        // Two warnings in a group auto-suspend the member in that group.
        if ($warnings >= 2 && $membership->isActive()) {
            $updates['Member_Status'] = GroupMember::STATUS_SUSPENDED;
        }

        $group->members()->updateExistingPivot($user->id, $updates);

        $action = ($updates['Member_Status'] ?? null) === GroupMember::STATUS_SUSPENDED
            ? 'group_suspend'
            : 'group_warning';

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'group_id' => $group->id,
            'action' => $action,
            'reason' => $request->reason,
        ]);

        $suspended = isset($updates['Member_Status']);

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'warning',
            'Notification_Title' => $suspended
                ? '⛔ Suspended from '.$group->Group_Name
                : "⚠️ Warning {$warnings}/2 in {$group->Group_Name}",
            'Message' => $suspended
                ? 'You received 2 warnings and have been suspended from this group.'
                    .($request->reason ? ' Reason: '.$request->reason : '')
                : 'You received a warning from a group admin.'
                    .($request->reason ? ' Reason: '.$request->reason : '')
                    .' One more warning will suspend you from this group.',
            'Is_Read' => false,
        ]);

        return back()->with(
            'success',
            $suspended
                ? "{$user->name} was warned ({$warnings}/2) and suspended from this group."
                : "Warning {$warnings}/2 issued to {$user->name} in this group."
        );
    }

    public function suspend(Request $request, Group $group, User $user)
    {
        $this->authorizeModeration($group, $user);

        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $group->members()->updateExistingPivot($user->id, [
            'Member_Status' => GroupMember::STATUS_SUSPENDED,
        ]);

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'group_id' => $group->id,
            'action' => 'group_suspend',
            'reason' => $request->reason,
        ]);

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'warning',
            'Notification_Title' => '⛔ Suspended from '.$group->Group_Name,
            'Message' => 'A group admin suspended you from this group.'
                .($request->reason ? ' Reason: '.$request->reason : ''),
            'Is_Read' => false,
        ]);

        return back()->with('success', "{$user->name} has been suspended from this group.");
    }

    public function block(Request $request, Group $group, User $user)
    {
        $this->authorizeModeration($group, $user);

        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $group->members()->updateExistingPivot($user->id, [
            'Member_Status' => GroupMember::STATUS_BLOCKED,
        ]);

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'group_id' => $group->id,
            'action' => 'group_block',
            'reason' => $request->reason,
        ]);

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'warning',
            'Notification_Title' => '🚫 Blocked from '.$group->Group_Name,
            'Message' => 'A group admin blocked you from this group.'
                .($request->reason ? ' Reason: '.$request->reason : ''),
            'Is_Read' => false,
        ]);

        return back()->with('success', "{$user->name} has been blocked from this group.");
    }

    public function reinstate(Group $group, User $user)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403, 'Only group admins can reinstate members.');
        abort_unless($group->isMember($user->id), 404, 'User is not a member of this group.');

        $group->members()->updateExistingPivot($user->id, [
            'Member_Status' => GroupMember::STATUS_ACTIVE,
            'warnings' => 0,
        ]);
        $this->quizNotifications->notifyNewlyActiveMember($user, $group);

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'group_id' => $group->id,
            'action' => 'group_reinstate',
            'reason' => 'Reinstated by group admin',
        ]);

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'warning',
            'Notification_Title' => '✅ Reinstated in '.$group->Group_Name,
            'Message' => 'A group admin reinstated your access to this group.',
            'Is_Read' => false,
        ]);

        return back()->with('success', "{$user->name} has been reinstated in this group.");
    }

    private function authorizeModeration(Group $group, User $user): void
    {
        abort_unless(Auth::user()->canManageGroup($group), 403, 'Only group admins can moderate members.');
        abort_unless($group->isMember($user->id), 404, 'User is not a member of this group.');
        abort_unless((int) $user->id !== (int) Auth::id(), 422, 'You cannot moderate yourself.');

        // Protect other group admins unless the actor is a system admin.
        if ($group->isGroupAdmin($user->id) && ! Auth::user()->isAdmin()) {
            abort(403, 'Only a system admin can moderate another group admin.');
        }
    }
}
