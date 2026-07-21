<?php
namespace App\Services;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Http\Client\ConnectionException;
use App\Models\SyncQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml.url', 'http://localhost:5000');
    }

    public function classifyTopic($title, $content)
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/classify", [
                'title' => $title,
                'content' => $content,
            ]);

            if ($response->successful()) {
                return $response->json('category');
            }
        } catch (ConnectionException $e) {
            Log::warning('ML classify service unavailable', ['error' => $e->getMessage()]);
        }

        return 'unclassified';
    }

    public function getRecommendations($userId)
    {
        $topics = Topic::query()
            ->select('id', 'Title', 'Topic_Description')
            ->get()
            ->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->Title,
                    'description' => $topic->Topic_Description,
                ];
            })
            ->toArray();

        $postHistory = Post::query()
            ->where('Created_By', $userId)
            ->select('Topic_ID', 'Post_Content')
            ->get()
            ->map(function ($post) {
                return [
                    'topic_id' => $post->Topic_ID,
                    'engagement_score' => 0.8,
                    'title' => $post->Post_Content,
                ];
            })
            ->toArray();
        
        $viewHistory = SyncQueue::query()
            ->where('user_id', $userId)
            ->where('action_type','view_topic')
            ->get()
            ->map(function ($queueItem) {
                $payload = is_string($queueItem->payload) ? json_decode($queueItem->payload, true) : $queueItem->payload;
                return [
                    'topic_id' => (int) ($payload['topic_id'] ?? 0),
                    'engagement_score' => 0.2,
                    'title' => $payload['topic_title'] ?? $payload['title'] ?? '',
                ];
            })
            ->filter(function ($view) {
                return $view['topic_id'] > 0;
            });

        $history = collect($postHistory)->concat($viewHistory)->values()->toArray();

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/recommend", [
                'user_id' => $userId,
                'topics' => $topics,
                'history' => $history,
                'limit' => 5,
            ]);

            if ($response->successful()) {
                return $response->json('recommendations', []);
            }
        } catch (ConnectionException $e) {
            Log::warning('ML recommendation service unavailable', ['error' => $e->getMessage()]);
        }

        return [];
    }
}