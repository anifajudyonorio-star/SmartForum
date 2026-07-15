<?php
namespace App\Services;

use App\Models\Topic;
use App\Models\Post;
use Illuminate\Support\Facades\Http;

class MachineLearningService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml.url', 'http://localhost:5000');
    }

    public function classifyTopic($title, $content)
    {
        $response = Http::post("{$this->baseUrl}/classify", [
            'title' => $title,
            'content' => $content,
        ]);

        if ($response->successful()) {
            return $response->json('category');
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

        $history = Post::query()
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

        $response = Http::timeout(5)->post("{$this->baseUrl}/recommend", [
            'user_id' => $userId,
            'topics' => $topics,
            'history' => $history,
            'limit' => 5,
        ]);

        if ($response->successful()) {
            return $response->json('recommendations', []);
        }

        return [];
    }
}