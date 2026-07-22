<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ModerationLog;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function report(Post $post, User $reporter, ?string $reason = null): Report
    {
        $post->loadMissing('topic.group');

        $group = $post->topic?->group;
        if (! $group) {
            throw ValidationException::withMessages([
                'post' => 'This post is not linked to a group discussion.',
            ]);
        }

        if (! $reporter->canParticipateInGroup($group)) {
            throw ValidationException::withMessages([
                'post' => 'You must be an active group member to report posts.',
            ]);
        }

        if ((int) $post->Created_By === $reporter->id) {
            throw ValidationException::withMessages([
                'post' => 'You cannot report your own message.',
            ]);
        }

        if ($post->trashed()) {
            throw ValidationException::withMessages([
                'post' => 'This message has already been reported and is awaiting review.',
            ]);
        }

        if (Report::query()
            ->where('post_id', $post->id)
            ->where('reporter_id', $reporter->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'post' => 'You have already reported this message.',
            ]);
        }

        $report = Report::create([
            'post_id' => $post->id,
            'reporter_id' => $reporter->id,
            'group_id' => $group->id,
            'reason' => $reason,
            'status' => Report::STATUS_PENDING,
        ]);

        $post->delete();

        ModerationLog::create([
            'user_id' => $post->Created_By,
            'admin_id' => $reporter->id,
            'group_id' => $group->id,
            'action' => 'post_reported',
            'reason' => $reason,
        ]);

        $this->notifyGroupAdmins($group, $post, $reporter, $reason);

        return $report->load(['post.user', 'post.topic', 'reporter']);
    }

    public function pendingForGroup(Group $group): Collection
    {
        return Report::query()
            ->pending()
            ->where('group_id', $group->id)
            ->with([
                'post.user',
                'post.topic',
                'reporter',
            ])
            ->latest()
            ->get();
    }

    public function restore(Report $report, User $admin): Report
    {
        $this->authorizeReview($report, $admin);

        $post = $report->post;
        if ($post && $post->trashed()) {
            $post->restore();
        }

        $report->update([
            'status' => Report::STATUS_RESTORED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        ModerationLog::create([
            'user_id' => $post?->Created_By,
            'admin_id' => $admin->id,
            'group_id' => $report->group_id,
            'action' => 'post_report_restored',
            'reason' => 'Report dismissed and message restored.',
        ]);

        return $report->fresh(['post.user', 'post.topic', 'reporter', 'reviewer']);
    }

    public function removePermanently(Report $report, User $admin): void
    {
        $this->authorizeReview($report, $admin);

        $post = $report->post;

        $report->update([
            'status' => Report::STATUS_REMOVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        ModerationLog::create([
            'user_id' => $post?->Created_By,
            'admin_id' => $admin->id,
            'group_id' => $report->group_id,
            'action' => 'post_report_removed',
            'reason' => 'Report upheld and message permanently removed.',
        ]);

        if ($post) {
            $post->forceDelete();
        }
    }

    private function authorizeReview(Report $report, User $admin): void
    {
        $report->loadMissing('group');

        if (! $report->isPending()) {
            throw ValidationException::withMessages([
                'report' => 'This report has already been reviewed.',
            ]);
        }

        if (! $admin->canManageGroup($report->group)) {
            throw ValidationException::withMessages([
                'report' => 'Only group admins can review reported posts.',
            ]);
        }
    }

    private function notifyGroupAdmins(Group $group, Post $post, User $reporter, ?string $reason): void
    {
        $post->loadMissing('topic', 'user');

        $admins = $group->members()
            ->wherePivot('Member_Role', GroupMember::ROLE_ADMIN)
            ->wherePivot('Member_Status', GroupMember::STATUS_ACTIVE)
            ->get();

        $message = $reporter->name.' reported a message in "'.$post->topic?->Title.'" as irrelevant.';
        if ($reason) {
            $message .= ' Reason: '.$reason;
        }

        foreach ($admins as $admin) {
            if ((int) $admin->id === $reporter->id) {
                continue;
            }

            Notification::create([
                'user_ID' => $admin->id,
                'Notification_Type' => 'post_report',
                'Notification_Title' => 'Reported message in '.$group->Group_Name,
                'Message' => $message,
                'Is_Read' => false,
                'Post_ID' => $post->id,
                'group_id' => $group->id,
            ]);
        }
    }
}
