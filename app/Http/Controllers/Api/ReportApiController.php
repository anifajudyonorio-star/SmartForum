<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportApiController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function store(Request $request, Post $post)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $post->loadMissing('topic.group');

        abort_unless(
            $post->topic?->group && Auth::user()->canViewGroup($post->topic->group),
            403
        );

        $report = $this->reports->report(
            $post,
            Auth::user(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Message reported. A group admin will review it shortly.',
            'report' => $this->formatReport($report),
        ], 201);
    }

    public function index(Group $group)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);

        $reports = $this->reports->pendingForGroup($group)
            ->map(fn (Report $report) => $this->formatReport($report));

        return response()->json([
            'reports' => $reports,
        ]);
    }

    public function restore(Group $group, Report $report)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless((int) $report->group_id === (int) $group->id, 404);

        $report = $this->reports->restore($report, Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Message restored to the discussion.',
            'report' => $this->formatReport($report),
        ]);
    }

    public function destroy(Group $group, Report $report)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless((int) $report->group_id === (int) $group->id, 404);

        $this->reports->removePermanently($report, Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Reported message permanently removed.',
        ]);
    }

    private function formatReport(Report $report): array
    {
        $report->loadMissing(['post.user', 'post.topic', 'reporter', 'reviewer']);

        return [
            'id' => $report->id,
            'status' => $report->status,
            'reason' => $report->reason,
            'created_at' => $report->created_at?->toIso8601String(),
            'reviewed_at' => $report->reviewed_at?->toIso8601String(),
            'reporter' => [
                'id' => $report->reporter?->id,
                'name' => $report->reporter?->name,
            ],
            'post' => [
                'id' => $report->post?->id,
                'content' => $report->post?->Post_Content,
                'author_name' => $report->post?->user?->name,
                'topic_title' => $report->post?->topic?->Title,
                'topic_id' => $report->post?->topic?->id,
            ],
        ];
    }
}
