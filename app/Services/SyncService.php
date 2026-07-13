<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Device;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\SyncLog;
use App\Models\SyncQueue;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public function queueAction($userId, $actionType, $payload)
    {
        return SyncQueue::create([
            'user_id'     => $userId,
            'action_type' => $actionType,
            'payload'     => $payload,
            'is_synced'   => false,
        ]);
    }

    public function pendingActions($userId)
    {
        return SyncQueue::where('user_id', $userId)
            ->where('is_synced', false)
            ->orderBy('created_at')
            ->get();
    }

    public function markAsSynced(SyncQueue $action)
    {
        $action->update(['is_synced' => true, 'synced_at' => Carbon::now()]);
    }

    public function uploadOfflineData(Request $request)
    {
        $request->validate([
            'actions'               => 'required|array',
            'actions.*.action_type' => 'required|string',
            'actions.*.payload'     => 'required|array',
        ]);

        $userId = $request->user()->id;

        foreach ($request->actions as $action) {
            $this->queueAction($userId, $action['action_type'], $action['payload']);
        }

        return response()->json(['success' => true, 'message' => 'Actions queued for sync']);
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
            $pending   = $this->pendingActions($userId);
            $processed = 0;
            $conflicts = [];
            $errors    = [];

            foreach ($pending as $action) {
                try {
                    $this->processAction($action);
                    $this->markAsSynced($action);
                    $processed++;
                } catch (ConflictException $e) {
                    $this->markAsSynced($action);
                    $conflicts[] = [
                        'action_id'   => $action->id,
                        'action_type' => $action->action_type,
                        'reason'      => $e->getMessage(),
                    ];
                } catch (\Throwable $e) {
                    $errors[] = ['action_id' => $action->id, 'error' => $e->getMessage()];
                }
            }

            $status = match (true) {
                !empty($errors)    => 'partial',
                !empty($conflicts) => 'conflict',
                default            => 'success',
            };

            SyncLog::create([
                'user_id'        => $userId,
                'device_id'      => $device->id,
                'records_synced' => $processed,
                'status'         => $status,
                'synced_at'      => Carbon::now(),
            ]);

            $device->update(['last_sync' => Carbon::now(), 'is_online' => true]);

            return response()->json([
                'success'        => true,
                'synced_records' => $processed,
                'conflicts'      => $conflicts,
                'errors'         => $errors,
            ]);
        });
    }


    public function getPendingData(Request $request)
    {
        return response()->json([
            'success'         => true,
            'pending_actions' => $this->pendingActions($request->user()->id),
        ]);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_id'   => 'required|string',
            'device_name' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        $device = Device::updateOrCreate(
            ['device_id' => $request->device_id, 'user_id' => $request->user()->id],
            [
                'device_name' => $request->device_name,
                'device_type' => $request->device_type ?? 'browser',
                'is_online'   => true,
                'status'      => 'online',
            ]
        );

        return response()->json(['success' => true, 'device' => $device]);
    }

    private function processAction(SyncQueue $action): void
    {
        match ($action->action_type) {
            'create_post'  => $this->processCreatePost($action),
            'create_topic' => $this->processCreateTopic($action),
            'submit_quiz'  => $this->processSubmitQuiz($action),
            default        => throw new \InvalidArgumentException("Unknown action type: {$action->action_type}"),
        };
    }

    private function processCreatePost(SyncQueue $action): void
    {
        $p = $action->payload;

        $topic = Topic::find($p['topic_id']);
        if (! $topic) {
            throw new ConflictException('The topic no longer exists.');
        }

        $isMember = GroupMember::where('Group_ID', $topic->Group_ID)
            ->where('User_ID', $action->user_id)
            ->exists();

        if (! $isMember) {
            throw new ConflictException('You are no longer a member of this group.');
        }

        Post::create([
            'Topic_ID'       => $p['topic_id'],
            'Parent_Post_ID' => $p['parent_post_id'] ?? null,
            'Created_By'     => $action->user_id,
            'Post_Content'   => $p['content'],
        ]);
    }

    private function processCreateTopic(SyncQueue $action): void
    {
        $p = $action->payload;

        $group = Group::find($p['group_id']);
        if (! $group) {
            throw new ConflictException('The group no longer exists.');
        }

        $isMember = GroupMember::where('Group_ID', $p['group_id'])
            ->where('User_ID', $action->user_id)
            ->exists();

        if (! $isMember) {
            throw new ConflictException('You are no longer a member of this group.');
        }

        Topic::create([
            'Title'             => $p['title'],
            'Topic_Description' => $p['description'] ?? null,
            'Group_ID'          => $p['group_id'],
            'Created_By'        => $action->user_id,
        ]);
    }

    private function processSubmitQuiz(SyncQueue $action): void
    {
        $p = $action->payload;

        if (QuizResult::where('quiz_id', $p['quiz_id'])->where('user_id', $action->user_id)->exists()) {
            throw new ConflictException('You have already submitted this quiz.');
        }

        $quiz = Quiz::with('questions.options')->find($p['quiz_id']);
        if (! $quiz) {
            throw new ConflictException('This quiz no longer exists.');
        }

        if ($quiz->status !== 'Active') {
            throw new ConflictException("Quiz \"{$quiz->title}\" is no longer active.");
        }

        $now = Carbon::now();
        if ($quiz->end_time && $now->isAfter($quiz->end_time)) {
            throw new ConflictException("Quiz \"{$quiz->title}\" has already closed.");
        }

        $score   = 0;
        $answers = $p['answers'] ?? [];

        foreach ($quiz->questions as $question) {
            if (! in_array($question->question_type, ['Multiple Choice', 'True/False'])) {
                continue;
            }
            $selected = $answers[$question->id] ?? null;
            $correct  = $question->options->firstWhere('is_correct', true);
            if ($correct && (int) $selected === (int) $correct->id) {
                $score += $question->marks;
            }
        }

        $participationMarks = (int) ($quiz->participation_marks ?? 0);

        QuizResult::create([
            'quiz_id'             => $quiz->id,
            'user_id'             => $action->user_id,
            'score'               => $score,
            'participation_marks' => $participationMarks,
            'total_score'         => $score + $participationMarks,
        ]);
    }
}
