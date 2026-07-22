<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuizAnnouncementApiController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', QuizAnnouncement::class);

        $categories = QuizCategory::manageableBy(Auth::user())
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        $announcements = QuizAnnouncement::with(['category', 'author'])
            ->whereHas('category', fn ($query) => $query->manageableBy(Auth::user()))
            ->latest()
            ->get();

        return response()->json([
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])->values(),
            'announcements' => $announcements->map(fn ($announcement) => $this->serializeAnnouncement($announcement))->values(),
            'count' => $announcements->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:quiz_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $category = QuizCategory::findOrFail($validated['category_id']);
        $this->authorize('create', [QuizAnnouncement::class, $category]);

        $announcement = QuizAnnouncement::create([
            'category_id' => $category->id,
            'created_by' => Auth::id(),
            'title' => $validated['title'],
            'message' => $validated['message'],
        ]);

        $announcement->load(['category', 'author']);

        return response()->json([
            'message' => 'Announcement posted to "'.$category->category_name.'".',
            'announcement' => $this->serializeAnnouncement($announcement),
        ], 201);
    }

    public function destroy(QuizAnnouncement $quizAnnouncement)
    {
        $this->authorize('delete', $quizAnnouncement);

        $quizAnnouncement->delete();

        return response()->json([
            'message' => 'Announcement deleted.',
        ]);
    }

    public function studentFeed()
    {
        $this->authorize('viewStudentFeed', QuizAnnouncement::class);

        $user = Auth::user();
        $enrolledCategory = $user->enrolledCategory();

        $announcements = collect();
        if ($enrolledCategory !== null) {
            $announcements = QuizAnnouncement::with(['category', 'author'])
                ->where('category_id', $enrolledCategory->id)
                ->latest()
                ->get();
        }

        return response()->json([
            'enrolled_category' => $enrolledCategory ? [
                'id' => $enrolledCategory->id,
                'name' => $enrolledCategory->category_name,
            ] : null,
            'announcements' => $announcements->map(fn ($announcement) => $this->serializeAnnouncement($announcement))->values(),
        ]);
    }

    private function serializeAnnouncement(QuizAnnouncement $announcement): array
    {
        $user = Auth::user();

        return [
            'id' => $announcement->id,
            'category_id' => $announcement->category_id,
            'category_name' => $announcement->category->category_name ?? null,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'message_preview' => Str::limit($announcement->message, 120),
            'author_name' => $announcement->author->name ?? 'Lecturer',
            'created_at' => $announcement->created_at?->format('M j, Y g:i A'),
            'can_delete' => $user ? $user->can('delete', $announcement) : false,
        ];
    }
}
