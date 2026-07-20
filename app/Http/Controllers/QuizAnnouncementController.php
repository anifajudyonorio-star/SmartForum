<?php

namespace App\Http\Controllers;

use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use Illuminate\Http\Request;

class QuizAnnouncementController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', QuizAnnouncement::class);

        $categories = QuizCategory::manageableBy(auth()->user())
            ->orderBy('category_name')
            ->get();

        $announcements = QuizAnnouncement::with(['category', 'author'])
            ->whereHas('category', fn ($query) => $query->manageableBy(auth()->user()))
            ->latest()
            ->get();

        return view('quiz_announcements.index', compact('categories', 'announcements'));
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

        QuizAnnouncement::create([
            'category_id' => $category->id,
            'created_by' => auth()->id(),
            'title' => $validated['title'],
            'message' => $validated['message'],
        ]);

        return redirect()
            ->route('quiz-announcements.index')
            ->with('success', 'Announcement posted to "'.$category->category_name.'".');
    }

    public function destroy(QuizAnnouncement $quiz_announcement)
    {
        $this->authorize('delete', $quiz_announcement);

        $quiz_announcement->delete();

        return redirect()
            ->route('quiz-announcements.index')
            ->with('success', 'Announcement deleted.');
    }

    public function studentIndex()
    {
        $this->authorize('viewStudentFeed', QuizAnnouncement::class);

        $user = auth()->user();
        $enrolledCategory = $user->enrolledCategory();

        $announcements = collect();
        if ($enrolledCategory !== null) {
            $announcements = QuizAnnouncement::with(['category', 'author'])
                ->where('category_id', $enrolledCategory->id)
                ->latest()
                ->get();
        }

        return view('quiz_announcements.student', compact('announcements', 'enrolledCategory'));
    }
}
