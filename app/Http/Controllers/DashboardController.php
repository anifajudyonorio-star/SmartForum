<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function latestPosts()
    {
        $groupIds = Auth::user()->groups()->pluck('groups.id');

        $latestPosts = Post::with('topic')
            ->whereIn('Topic_ID', Topic::whereIn('Group_ID', $groupIds)->pluck('id'))
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.partials.latest-posts', compact('latestPosts'));
    }

    public function index()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return view('dashboard.admin', $this->adminData());
        }

        if ($user->isLecturer()) {
            return view('dashboard.lecturer', $this->lecturerData());
        }

        return view('dashboard.student', $this->studentData());
    }

    private function studentData(): array
    {
        $groupIds = Auth::user()->groups()->pluck('groups.id');

        return [
            'myPosts' => Post::where('Created_By', Auth::id())->count(),
            'myTopics' => Topic::where('Created_By', Auth::id())->count(),
            'myReplies' => Post::whereNotNull('Parent_Post_ID')
                ->where('Created_By', Auth::id())
                ->count(),
            'groups' => $groupIds->count(),
            'recentTopics' => Topic::with('group')
                ->whereIn('Group_ID', $groupIds)
                ->latest()
                ->take(5)
                ->get(),
            'latestPosts' => Post::with('topic')
                ->whereIn('Topic_ID', Topic::whereIn('Group_ID', $groupIds)->pluck('id'))
                ->where('created_at', '>=', now()->subDay())
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    private function lecturerData(): array
    {
        $lecturerGroupIds = Auth::user()->groups()->pluck('groups.id');

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
            ])->get();

        foreach ($participants as $participant) {
            $participant->score =
                $participant->posts_count +
                $participant->replies_count;
        }

        return [
            'myGroups' => Auth::user()->groups()->count(),
            'myTopics' => Topic::where('Created_By', Auth::id())->count(),
            'participants' => $participants->sortByDesc('score')->take(10),
        ];
    }

    private function adminData(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalGroups' => Group::count(),
            'totalTopics' => Topic::count(),
            'totalPosts' => Post::count(),
            'topGroups' => Group::withCount('topics')
                ->orderByDesc('topics_count')
                ->take(5)
                ->get(),
            'topTopics' => Topic::withCount('posts')
                ->orderByDesc('posts_count')
                ->take(5)
                ->get(),
        ];
    }
}