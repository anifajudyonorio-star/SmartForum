<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\GroupJoinService;
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
            ->values();

        $topicIds = $recommendations->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($topicIds === []) {
            return response()->json([
                'user_id' => $user->id,
                'recommendations' => [],
            ]);
        }

        $topics = Topic::with(['user', 'group'])
            ->whereIn('id', $topicIds)
            ->whereHas('group')
            ->get()
            ->map(function (Topic $topic) use ($recommendations, $user) {
                $match = $recommendations->firstWhere('id', (int) $topic->id);

                return [
                    'id' => $topic->id,
                    'title' => $topic->Title,
                    'description' => $topic->Topic_Description,
                    'score' => $match['score'] ?? 0,
                    'group_id' => $topic->Group_ID,
                    'group_name' => $topic->group->Group_Name ?? '',
                    'can_view' => $topic->group ? $user->canViewGroup($topic->group) : false,
                    'join_status' => $topic->group
                        ? GroupJoinService::joinStatusFor($user, $topic->group)
                        : 'none',
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();

        return response()->json([
            'user_id' => $user->id,
            'recommendations' => $topics,
        ]);
    }
}
