<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Device;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use App\Models\Topic;
use App\Models\TopicView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncService
{
    public function __construct(private readonly QuizSubmissionService $quizSubmissions) {}

    public function queueAction(int $userId, string $actionUuid, string $actionType, array $payload): SyncQueue
    {
        return SyncQueue::create([
            'action_uuid' => $actionUuid,
            'user_id' => $userId,
            'action_type' => $actionType,
            'payload' => $payload,
            'is_synced' => false,
            'sync_status' => SyncQueue::STATUS_PENDING,
        ]);
    }

    public function pendingActions($userId, bool $lockForUpdate = false)
    {
        $query = SyncQueue::where('user_id', $userId)
            ->where('is_synced', false)
            ->orderBy('created_at')
            ->orderBy('id');

        return ($lockForUpdate ? $query->lockForUpdate() : $query)->get();
    }

    public function markAsSynced(SyncQueue $action)
    {
        $action->update([
            'is_synced' => true,
            'sync_status' => SyncQueue::STATUS_SUCCEEDED,
            'last_error' => null,
            'synced_at' => Carbon::now(),
        ]);
    }

    public function markAsFailed(SyncQueue $action, string $reason): void
    {
        $action->update([
            'is_synced' => false,
            'sync_status' => SyncQueue::STATUS_FAILED,
            'last_error' => $reason,
        ]);
    }

    public function uploadOfflineData(Request $request)
    {
        $validated = $request->validate([
            'actions' => ['required', 'array', 'max:50'],
            'actions.*.action_uuid' => ['required', 'uuid'],
            'actions.*.action_type' => [
                'required',
                Rule::in(['create_post', 'create_topic', 'submit_quiz', 'view_topic']),
            ],
            'actions.*.payload' => ['required', 'array'],
        ]);

        $userId = (int) $request->user()->id;
        $acknowledgements = [];
        $actions = [];

        foreach ($validated['actions'] as $index => $action) {
            $action['payload'] = $this->validateActionPayload(
                $action['action_type'],
                $action['payload'],
                $index,
            );
            $actions[] = $action;
        }

        foreach ($actions as $action) {
            $payload = $action['payload'];
            $existing = SyncQueue::query()
                ->where('user_id', $userId)
                ->where('action_uuid', $action['action_uuid'])
                ->first();
            $existing ??= $this->adoptLegacyAction(
                $userId,
                $action['action_uuid'],
                $action['action_type'],
                $payload,
            );

            if ($existing) {
                $acknowledgements[] = $this->duplicateAcknowledgement($existing, $action, $payload);

                continue;
            }

            try {
                $queued = $this->queueAction(
                    $userId,
                    $action['action_uuid'],
                    $action['action_type'],
                    $payload,
                );
                $acknowledgements[] = $this->actionAcknowledgement($queued, 'queued');
            } catch (QueryException $exception) {
                $existing = SyncQueue::query()
                    ->where('user_id', $userId)
                    ->where('action_uuid', $action['action_uuid'])
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                $acknowledgements[] = $this->duplicateAcknowledgement($existing, $action, $payload);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Offline actions acknowledged.',
            'actions' => $acknowledgements,
        ]);
    }

    public function sync(Request $request)
    {
        $request->validate(['device_id' => 'required']);

        $userId = $request->user()->id;

        $device = Device::where(function ($q) use ($request) {
            $q->where('id', $request->device_id)
                ->orWhere('device_id', $request->device_id);
        })
            ->where('user_id', $userId)
            ->first();

        if (! $device) {
            return response()->json(['success' => false, 'message' => 'Device not registered'], 404);
        }

        return DB::transaction(function () use ($userId, $device) {
            $pending = $this->pendingActions($userId, lockForUpdate: true);
            $processed = 0;
            $conflicts = [];
            $errors = [];
            $actionResults = [];

            foreach ($pending as $action) {
                try {
                    $this->processAction($action);
                    $this->markAsSynced($action);
                    $processed++;
                    $actionResults[] = $this->actionAcknowledgement($action, 'succeeded');
                } catch (ConflictException|AuthorizationException|ValidationException $e) {
                    $reason = $this->actionFailureMessage($e);
                    $this->markAsFailed($action, $reason);
                    $conflicts[] = [
                        'action_id' => $action->id,
                        'action_uuid' => $action->action_uuid,
                        'action_type' => $action->action_type,
                        'reason' => $reason,
                    ];
                    $actionResults[] = $this->actionAcknowledgement($action, 'failed', $reason);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'action_id' => $action->id,
                        'action_uuid' => $action->action_uuid,
                        'error' => 'A temporary server error prevented this action from syncing.',
                    ];
                    $actionResults[] = $this->actionAcknowledgement(
                        $action,
                        'failed',
                        'A temporary server error prevented this action from syncing; retry later.',
                    );
                }
            }

            $status = match (true) {
                ! empty($errors) => 'partial',
                ! empty($conflicts) => 'conflict',
                default => 'success',
            };

            SyncLog::create([
                'user_id' => $userId,
                'device_id' => $device->id,
                'records_synced' => $processed,
                'status' => $status,
                'synced_at' => Carbon::now(),
            ]);

            $device->update(['last_sync' => Carbon::now(), 'is_online' => true]);

            return response()->json([
                'success' => true,
                'synced_records' => $processed,
                'actions' => $actionResults,
                'conflicts' => $conflicts,
                'errors' => $errors,
            ]);
        });
    }

    public function getPendingData(Request $request)
    {
        return response()->json([
            'success' => true,
            'pending_actions' => $this->pendingActions($request->user()->id),
        ]);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
            'device_name' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        $device = Device::updateOrCreate(
            ['device_id' => $request->device_id, 'user_id' => $request->user()->id],
            [
                'device_name' => $request->device_name,
                'device_type' => $request->device_type ?? 'browser',
                'is_online' => true,
                'status' => 'online',
            ]
        );

        return response()->json(['success' => true, 'device' => $device]);
    }

    private function processAction(SyncQueue $action): void
    {
        match ($action->action_type) {
            'create_post' => $this->processCreatePost($action),
            'create_topic' => $this->processCreateTopic($action),
            'submit_quiz' => $this->processSubmitQuiz($action),
            'view_topic' => $this->processViewTopic($action),
            default => throw new \InvalidArgumentException("Unknown action type: {$action->action_type}"),
        };
    }

    private function processCreatePost(SyncQueue $action): void
    {
        $p = $action->payload;

        $topicId  = $this->resolveTopicId((int) $action->user_id, (int) ($p['topic_id'] ?? 0));
        $content  = strip_tags((string) ($p['content'] ?? ''));
        $parentId = isset($p['parent_post_id']) ? (int) $p['parent_post_id'] : null;

        if (! $topicId || ! $content) {
            throw new ConflictException('Invalid post payload.');
        }

        $topic = Topic::find($topicId);
        if (! $topic) {
            throw new ConflictException('The topic no longer exists.');
        }

        $isMember = GroupMember::where('Group_ID', $topic->Group_ID)
            ->where('User_ID', $action->user_id)
            ->exists();

        if (! $isMember) {
            throw new ConflictException('You are no longer a member of this group.');
        }

        $post = Post::create([
            'Topic_ID'       => $topicId,
            'Parent_Post_ID' => $parentId,
            'Created_By'     => $action->user_id,
            'Post_Content'   => $content,
        ]);

        $author = User::find($action->user_id);
        if ($author) {
            PostVisibilityService::syncHiddenFrom(
                $post,
                $topic,
                $p['excluded_users'] ?? [],
                $author,
            );
        }

        $payload = $action->payload;
        $payload['server_post_id'] = $post->id;
        $action->update(['payload' => $payload]);
    }

    private function processCreateTopic(SyncQueue $action): void
    {
        $p = $action->payload;

        $groupId     = (int) ($p['group_id'] ?? 0);
        $title       = strip_tags((string) ($p['title'] ?? ''));
        $description = isset($p['description']) ? strip_tags((string) $p['description']) : null;

        if (! $groupId || ! $title) {
            throw new ConflictException('Invalid topic payload.');
        }

        $group = Group::find($groupId);
        if (! $group) {
            throw new ConflictException('The group no longer exists.');
        }

        $isMember = GroupMember::where('Group_ID', $groupId)
            ->where('User_ID', $action->user_id)
            ->exists();

        if (! $isMember) {
            throw new ConflictException('You are no longer a member of this group.');
        }

        $topic = Topic::create([
            'Title'             => $title,
            'Topic_Description' => $description,
            'Group_ID'          => $groupId,
            'Created_By'        => $action->user_id,
        ]);

        $payload = $action->payload;
        $payload['server_topic_id'] = $topic->id;
        $action->update(['payload' => $payload]);
    }

    private function processViewTopic(SyncQueue $action): void
    {
        $topicId = $this->resolveTopicId(
            (int) $action->user_id,
            (int) ($action->payload['topic_id'] ?? 0),
        );
        if ($topicId <= 0) {
            throw new ConflictException('Invalid topic view payload.');
        }

        $topic = Topic::find($topicId);
        if (! $topic) {
            throw new ConflictException('The topic no longer exists.');
        }

        $isMember = GroupMember::where('Group_ID', $topic->Group_ID)
            ->where('User_ID', $action->user_id)
            ->exists();

        if (! $isMember) {
            throw new ConflictException('You are no longer a member of this group.');
        }

        TopicView::updateOrCreate(
            [
                'user_id' => $action->user_id,
                'topic_id' => $topicId,
            ],
            ['viewed_at' => now()],
        );
    }

    public function status(Request $request)
    {
        $user = $request->user();

        $device = Device::where('user_id', $user->id)
            ->latest()
            ->first();

        $pending = SyncQueue::where('user_id', $user->id)
            ->where('is_synced', false)
            ->count();

        return response()->json([
            'success' => true,
            'online' => optional($device)->is_online ?? false,
            'device_name' => optional($device)->device_name,
            'last_sync' => optional($device?->last_sync)?->format('d M Y H:i:s'),
            'pending_actions' => $pending,
        ]);
    }

    private function processSubmitQuiz(SyncQueue $action): void
    {
        $p = $action->payload;
        $quiz = Quiz::find($p['quiz_id']);
        if (! $quiz) {
            throw new ConflictException('This quiz no longer exists.');
        }

        $user = User::find($action->user_id);
        if (! $user) {
            throw new ConflictException('You are no longer authorized to submit this quiz.');
        }

        $this->quizSubmissions->submit(
            $user,
            $quiz,
            (int) $p['attempt_id'],
            $p['answers'],
        );
    }

    private function validateActionPayload(string $actionType, array $payload, int $index): array
    {
        if (strlen((string) json_encode($payload)) > 65536) {
            throw ValidationException::withMessages([
                "actions.{$index}.payload" => 'Action payloads may not exceed 64 KB.',
            ]);
        }

        $rules = match ($actionType) {
            'create_post' => [
                'payload' => ['required', 'array'],
                'payload.topic_id' => ['required', 'integer', 'min:1'],
                'payload.parent_post_id' => ['nullable', 'integer', 'min:1'],
                'payload.content' => ['required', 'string', 'max:10000'],
                'payload.excluded_users' => ['nullable', 'array'],
                'payload.excluded_users.*' => ['integer', 'min:1'],
            ],
            'create_topic' => [
                'payload' => ['required', 'array'],
                'payload.group_id' => ['required', 'integer', 'min:1'],
                'payload.title' => ['required', 'string', 'max:255'],
                'payload.description' => ['nullable', 'string', 'max:5000'],
                'payload.client_topic_id' => ['nullable', 'integer', 'min:1'],
            ],
            'submit_quiz' => [
                'payload' => ['required', 'array:quiz_id,attempt_id,answers'],
                'payload.quiz_id' => ['required', 'integer', 'min:1'],
                'payload.attempt_id' => ['required', 'integer', 'min:1'],
                'payload.answers' => ['present', 'array', 'max:200'],
                'payload.answers.*' => ['required', 'integer', 'min:1'],
            ],
            'view_topic' => [
                'payload' => ['required', 'array'],
                'payload.topic_id' => ['required', 'integer', 'min:1'],
            ],
            default => throw new \InvalidArgumentException("Unknown action type: {$actionType}"),
        };

        $validator = Validator::make(['payload' => $payload], $rules);

        $validator->after(function ($validator) use ($actionType, $payload) {
            if ($actionType !== 'submit_quiz') {
                return;
            }

            foreach (array_keys($payload['answers'] ?? []) as $questionId) {
                if (! ctype_digit((string) $questionId) || (int) $questionId < 1) {
                    $validator->errors()->add(
                        'payload.answers',
                        'Answer keys must be positive question IDs.',
                    );

                    break;
                }
            }
        });

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors["actions.{$index}.{$field}"] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return $validator->validated()['payload'];
    }

    private function duplicateAcknowledgement(
        SyncQueue $existing,
        array $submittedAction,
        array $payload,
    ): array {
        if ($existing->action_type !== $submittedAction['action_type']
            || $existing->payload != $payload) {
            throw ValidationException::withMessages([
                'action_uuid' => 'An action UUID cannot be reused with different action data.',
            ]);
        }

        return match ($existing->sync_status) {
            SyncQueue::STATUS_SUCCEEDED => $this->actionAcknowledgement($existing, 'duplicate'),
            SyncQueue::STATUS_FAILED => $this->actionAcknowledgement(
                $existing,
                'failed',
                $existing->last_error ?? 'This action previously failed.',
            ),
            default => $this->actionAcknowledgement($existing, 'queued'),
        };
    }

    private function adoptLegacyAction(
        int $userId,
        string $actionUuid,
        string $actionType,
        array $payload,
    ): ?SyncQueue {
        $legacy = SyncQueue::query()
            ->where('user_id', $userId)
            ->whereNull('action_uuid')
            ->where('action_type', $actionType)
            ->get()
            ->first(fn (SyncQueue $action) => $action->payload == $payload);

        if (! $legacy) {
            return null;
        }

        $legacy->update([
            'action_uuid' => $actionUuid,
            'sync_status' => $legacy->is_synced
                ? SyncQueue::STATUS_SUCCEEDED
                : SyncQueue::STATUS_PENDING,
        ]);

        return $legacy->fresh();
    }

    private function actionAcknowledgement(
        SyncQueue $action,
        string $status,
        ?string $reason = null,
    ): array {
        $ack = [
            'action_id' => $action->id,
            'action_uuid' => $action->action_uuid,
            'action_type' => $action->action_type,
            'status' => $status,
            'reason' => $reason,
        ];

        $resourceId = $this->resourceIdForAction($action);
        if ($resourceId !== null) {
            $ack['resource_id'] = $resourceId;
        }

        return $ack;
    }

    private function resourceIdForAction(SyncQueue $action): ?int
    {
        $payload = $action->payload ?? [];

        return match ($action->action_type) {
            'create_topic' => isset($payload['server_topic_id']) ? (int) $payload['server_topic_id'] : null,
            'create_post' => isset($payload['server_post_id']) ? (int) $payload['server_post_id'] : null,
            default => null,
        };
    }

    private function resolveTopicId(int $userId, int $topicId): int
    {
        if ($topicId <= 0) {
            return 0;
        }

        if (Topic::find($topicId)) {
            return $topicId;
        }

        $prior = SyncQueue::query()
            ->where('user_id', $userId)
            ->where('action_type', 'create_topic')
            ->where('is_synced', true)
            ->orderBy('synced_at')
            ->get()
            ->first(fn (SyncQueue $action) => (int) ($action->payload['client_topic_id'] ?? 0) === $topicId);

        if ($prior && isset($prior->payload['server_topic_id'])) {
            return (int) $prior->payload['server_topic_id'];
        }

        return $topicId;
    }

    private function actionFailureMessage(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return (string) collect($exception->errors())->flatten()->first();
        }

        return $exception->getMessage();
    }
}
