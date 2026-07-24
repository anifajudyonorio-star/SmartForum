<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizCategoryApiController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', QuizCategory::class);

        $categories = QuizCategory::manageableBy(Auth::user())
            ->withCount('quizzes')
            ->orderBy('category_name')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (QuizCategory $category) => [
                'id' => $category->id,
                'name' => $category->category_name,
                'description' => $category->description,
                'quizzes_count' => (int) $category->quizzes_count,
            ])->values(),
            'count' => $categories->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', QuizCategory::class);

        $validated = $request->validate([
            'category_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = QuizCategory::create([
            'category_name' => $validated['category_name'],
            'description' => $validated['description'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Quiz title created successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->category_name,
                'description' => $category->description,
            ],
        ], 201);
    }
}
