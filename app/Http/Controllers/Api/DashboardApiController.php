<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\FormatsUserPermissions;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\GroupStatisticsService;
use Illuminate\Support\Facades\Auth;

class DashboardApiController extends Controller
{
    use FormatsUserPermissions;

    public function show()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return response()->json([
                'role' => 'admin',
                'permissions' => $this->userPermissions($user),
                'stats' => $this->adminStats(),
            ]);
        }

        if ($user->isLecturer()) {
            return response()->json([
                'role' => 'lecturer',
                'permissions' => $this->userPermissions($user),
                'stats' => $this->lecturerStats(),
                'group_admin_stats' => $this->groupAdminStats($user),
            ]);
        }

        return response()->json([
            'role' => 'student',
            'permissions' => $this->userPermissions($user),
            'stats' => $this->studentStats(),
            'group_admin_stats' => $this->groupAdminStats($user),
        ]);
    }

    private function groupAdminStats(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        $groupIds = $user->administeredGroups()->pluck('groups.id');
        if ($groupIds->isEmpty()) {
            return [];
        }

        return GroupStatisticsService::summaries($groupIds)
            ->map(fn ($summary) => [
                'group_id' => $summary->group->id,
                'group_name' => $summary->group->Group_Name,
                'members_count' => $summary->members_count,
                'topics_count' => $summary->topics_count,
                'posts_count' => $summary->posts_count,
            ])
            ->values()
            ->all();
    }

    private function studentStats(): array
    {
        $user = Auth::user();
        $groupIds = $user->viewableGroupIds();

        return [
            'my_posts' => Post::where('Created_By', $user->id)->count(),
            'my_topics' => Topic::where('Created_By', $user->id)->count(),
            'my_replies' => Post::whereNotNull('Parent_Post_ID')
                ->where('Created_By', $user->id)
                ->count(),
            'groups' => $groupIds->count(),
            'recent_topics' => Topic::with('group')
                ->whereIn('Group_ID', $groupIds)
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Topic $topic) => [
                    'title' => $topic->Title,
                    'group_name' => $topic->group->Group_Name ?? '',
                    'created_at' => $topic->created_at->diffForHumans(),
                ])
                ->values()
                ->all(),
            'latest_posts' => Post::with('topic')
                ->whereIn('Topic_ID', Topic::whereIn('Group_ID', $groupIds)->pluck('id'))
                ->where('created_at', '>=', now()->subDay())
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Post $post) => [
                    'content' => $post->Post_Content,
                    'created_at' => $post->created_at->diffForHumans(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function lecturerStats(): array
    {
        $user = Auth::user();
        $lecturerGroupIds = $user->viewableGroupIds();

        $participants = User::where(function ($query) {
            $query->whereNull('role')->orWhere('role', 'student');
        })
            ->whereHas('groups', function ($query) use ($lecturerGroupIds) {
                $query->whereIn('groups.id', $lecturerGroupIds);
            })
            ->withCount([
                'topics',
                'posts',
                'posts as replies_count' => function ($query) {
                    $query->whereNotNull('Parent_Post_ID');
                },
            ])
            ->get()
            ->map(function (User $participant) {
                return [
                    'name' => $participant->name,
                    'topics' => $participant->topics_count,
                    'posts' => $participant->posts_count,
                    'replies' => $participant->replies_count,
                    'score' => $participant->posts_count + $participant->replies_count,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take(10)
            ->all();

        return [
            'my_groups' => $user->viewableGroupsQuery()->count(),
            'my_topics' => Topic::where('Created_By', $user->id)->count(),
            'participants' => $participants,
        ];
    }

    private function adminStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_groups' => Group::count(),
            'total_topics' => Topic::count(),
            'total_posts' => Post::count(),
            'top_groups' => Group::withCount('topics')
                ->orderByDesc('topics_count')
                ->take(5)
                ->get()
                ->map(fn (Group $group) => [
                    'name' => $group->Group_Name,
                    'topics_count' => $group->topics_count,
                ])
                ->values()
                ->all(),
            'top_topics' => Topic::withCount('posts')
                ->orderByDesc('posts_count')
                ->take(5)
                ->get()
                ->map(fn (Topic $topic) => [
                    'title' => $topic->Title,
                    'posts_count' => $topic->posts_count,
                ])
                ->values()
                ->all(),
        ];
    }
}
