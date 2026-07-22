<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
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

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Message reported. A group admin will review it shortly.',
                'report_id' => $report->id,
            ]);
        }

        return back()->with('success', 'Message reported. A group admin will review it shortly.');
    }

    public function restore(Group $group, Report $report)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless((int) $report->group_id === (int) $group->id, 404);

        $this->reports->restore($report, Auth::user());

        return back()->with('success', 'Message restored to the discussion.');
    }

    public function destroy(Group $group, Report $report)
    {
        abort_unless(Auth::user()->canManageGroup($group), 403);
        abort_unless((int) $report->group_id === (int) $group->id, 404);

        $this->reports->removePermanently($report, Auth::user());

        return back()->with('success', 'Reported message permanently removed.');
    }
}
