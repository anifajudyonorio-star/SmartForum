<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\MachineLearningService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request, MachineLearningService $mlService)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $recommendations = collect($mlService->getRecommendations($user->id))
            ->filter(fn ($item) => isset($item['id']))
            ->values()
            ->all();

        return response()->json([
            'user_id' => $user->id,
            'recommendations' => $recommendations,
        ]);
    }
}
