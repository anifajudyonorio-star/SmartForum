<?php

namespace App\Http\Controllers;

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

        $recommendations = $mlService->getRecommendations($user->id);

        return response()->json([
            'user_id' => $user->id,
            'recommendations' => $recommendations,
        ]);
    }
}
