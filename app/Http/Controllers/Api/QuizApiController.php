<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\QuizNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class QuizApiController extends Controller
{
    public function __construct(private readonly QuizNotificationService $notifications) {}

    public function index()
    {
        $this->authorize('viewAny', Quiz::class);

        $quizzes = Quiz::manageableBy(Auth::user())
            ->with(['category', 'group'])
            ->withCount('questions')
            ->withSum('questions', 'marks')
            ->latest()
            ->get();

        return response()->json([
            'quizzes' => $quizzes->map(fn (Quiz $quiz) => $this->serializeQuiz($quiz))->values(),
            'count' => $quizzes->count(),
        ]);
    }

    public function publish(Quiz $quiz)
    {
        $this->authorize('publish', $quiz);

        $errors = $quiz->publicationErrors();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($quiz->isPublished()) {
            $this->notifications->notifyPublishedQuiz($quiz);

            return response()->json([
                'message' => 'Quiz is already published.',
                'quiz' => $this->serializeQuiz($quiz->fresh(['category', 'group'])),
            ]);
        }

        $quiz->update([
            'status' => now()->lt($quiz->start_time)
                ? Quiz::STATUS_SCHEDULED
                : Quiz::STATUS_ACTIVE,
        ]);

        $this->notifications->notifyPublishedQuiz($quiz->fresh());

        return response()->json([
            'message' => 'Quiz published successfully and all students have been notified.',
            'quiz' => $this->serializeQuiz($quiz->fresh(['category', 'group'])),
        ]);
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorize('delete', $quiz);

        if (! $quiz->canBeDeleted()) {
            throw ValidationException::withMessages([
                'quiz' => 'Published quizzes or quizzes with attempts/results cannot be deleted.',
            ]);
        }

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully.',
        ]);
    }

    private function serializeQuiz(Quiz $quiz): array
    {
        $user = Auth::user();

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'category_id' => $quiz->category_id,
            'category_name' => $quiz->category->category_name ?? null,
            'group_id' => $quiz->group_id,
            'group_name' => $quiz->group?->Group_Name ?? 'Unassigned',
            'questions_count' => (int) $quiz->questions_count,
            'maximum_marks' => $quiz->authoredMaximumTotal(),
            'duration' => (int) $quiz->duration,
            'participation_marks' => (int) $quiz->participation_marks,
            'start_time' => $quiz->start_time?->format('M j, Y g:i A'),
            'end_time' => $quiz->end_time?->format('M j, Y g:i A'),
            'status' => $quiz->status,
            'lifecycle_status' => $quiz->lifecycleStatus(),
            'is_published' => $quiz->isPublished(),
            'can_publish' => $user->can('publish', $quiz) && ! $quiz->isPublished() && $quiz->questions_count > 0,
            'can_delete' => $user->can('delete', $quiz) && $quiz->canBeDeleted(),
        ];
    }
}
