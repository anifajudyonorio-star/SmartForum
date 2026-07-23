<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Topic;
use App\Models\TopicView;
use App\Models\User;
use App\Models\SyncQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml.url', 'http://localhost:5001');
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
        $user = User::query()->find($userId);
        if (! $user) {
            return [];
        }

        $memberGroupIds = $user->viewableGroupIds()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $history = $this->buildEngagementHistory($userId);
        $engagedTopicIds = $this->extractInteractivelyEngagedTopicIds($history);

        if ($engagedTopicIds === []) {
            return [];
        }

        $engagedGroupIds = Topic::query()
            ->whereIn('id', $engagedTopicIds)
            ->whereNotNull('Group_ID')
            ->pluck('Group_ID')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $topics = Topic::query()
            ->with('group:id,Group_Name')
            ->whereNotNull('Group_ID')
            ->when($engagedTopicIds !== [], fn ($query) => $query->whereNotIn('id', $engagedTopicIds))
            ->when($engagedGroupIds !== [], fn ($query) => $query->whereNotIn('Group_ID', $engagedGroupIds))
            ->select('id', 'Title', 'Topic_Description', 'Group_ID')
            ->get()
            ->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->Title,
                    'description' => $topic->Topic_Description,
                    'group_id' => (int) $topic->Group_ID,
                    'group_name' => $topic->group?->Group_Name,
                ];
            })
            ->values()
            ->toArray();

        if ($topics === []) {
            return [];
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/recommend", [
                'user_id' => $userId,
                'topics' => $topics,
                'history' => $this->buildInterestHistory($userId),
                'member_group_ids' => $memberGroupIds,
                'engaged_topic_ids' => $engagedTopicIds,
                'engaged_group_ids' => $engagedGroupIds,
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

    protected function buildEngagementHistory(int $userId): array
    {
        $postHistory = Post::query()
            ->where('Created_By', $userId)
            ->with('topic:id,Title,Topic_Description')
            ->select('Topic_ID', 'Parent_Post_ID', 'Post_Content')
            ->get()
            ->map(function ($post) {
                $isReply = ! empty($post->Parent_Post_ID);

                return [
                    'topic_id' => (int) $post->Topic_ID,
                    'engagement_type' => $isReply ? 'reply' : 'post',
                    'engagement_score' => $isReply ? 0.65 : 0.85,
                    'title' => $post->topic?->Title ?? '',
                    'description' => $post->topic?->Topic_Description ?? $post->Post_Content,
                ];
            });

        $createdTopicHistory = Topic::query()
            ->where('Created_By', $userId)
            ->select('id', 'Title', 'Topic_Description')
            ->get()
            ->map(function ($topic) {
                return [
                    'topic_id' => (int) $topic->id,
                    'engagement_type' => 'created_topic',
                    'engagement_score' => 1.0,
                    'title' => $topic->Title,
                    'description' => $topic->Topic_Description,
                ];
            });

        $viewHistory = TopicView::query()
            ->where('user_id', $userId)
            ->with('topic:id,Title,Topic_Description')
            ->get()
            ->map(function ($view) {
                return [
                    'topic_id' => (int) $view->topic_id,
                    'engagement_type' => 'view',
                    'engagement_score' => 0.35,
                    'title' => $view->topic?->Title ?? '',
                    'description' => $view->topic?->Topic_Description ?? '',
                ];
            })
            ->filter(fn ($view) => $view['topic_id'] > 0);

        $syncViewHistory = SyncQueue::query()
            ->where('user_id', $userId)
            ->where('action_type', 'view_topic')
            ->get()
            ->map(function ($queueItem) {
                $payload = is_string($queueItem->payload)
                    ? json_decode($queueItem->payload, true)
                    : $queueItem->payload;

                return [
                    'topic_id' => (int) ($payload['topic_id'] ?? 0),
                    'engagement_type' => 'view',
                    'engagement_score' => 0.35,
                    'title' => $payload['topic_title'] ?? $payload['title'] ?? '',
                    'description' => $payload['topic_description'] ?? $payload['description'] ?? '',
                ];
            })
            ->filter(fn ($view) => $view['topic_id'] > 0);

        return collect($postHistory)
            ->concat($createdTopicHistory)
            ->concat($viewHistory)
            ->concat($syncViewHistory)
            ->values()
            ->all();
    }

    protected function buildInterestHistory(int $userId): array
    {
        $aggregated = [];

        foreach ($this->buildEngagementHistory($userId) as $item) {
            $topicId = (int) ($item['topic_id'] ?? 0);
            if ($topicId <= 0) {
                continue;
            }

            if (! isset($aggregated[$topicId])) {
                $aggregated[$topicId] = $item;
                continue;
            }

            $aggregated[$topicId]['engagement_score'] = (float) $aggregated[$topicId]['engagement_score']
                + (float) ($item['engagement_score'] ?? 0);
        }

        return array_values($aggregated);
    }

    protected function extractInteractivelyEngagedTopicIds(array $history): array
    {
        return collect($history)
            ->filter(fn ($item) => in_array($item['engagement_type'] ?? '', ['view', 'post', 'reply'], true))
            ->pluck('topic_id')
            ->filter(fn ($topicId) => (int) $topicId > 0)
            ->map(fn ($topicId) => (int) $topicId)
            ->unique()
            ->values()
            ->all();
    }

    protected function extractInterestHistory(array $history): array
    {
        return collect($history)
            ->filter(fn ($item) => (int) ($item['topic_id'] ?? 0) > 0)
            ->values()
            ->all();
    }

    protected function extractEngagedTopicIds(array $history): array
    {
        return collect($history)
            ->pluck('topic_id')
            ->filter(fn ($topicId) => (int) $topicId > 0)
            ->map(fn ($topicId) => (int) $topicId)
            ->unique()
            ->values()
            ->all();
    }
}
