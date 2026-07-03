<?php

namespace App\Services;

use App\Models\SyncQueue;
use App\Models\SyncLog;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncService
{
    /**
     * Store offline action
     */
    public function queueAction($userId, $actionType, $payload)
    {
        return SyncQueue::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'payload' => $payload,
            'is_synced' => false,
        ]);
    }

    /**
     * Get pending actions
     */
    public function pendingActions($userId)
    {
        return SyncQueue::where('user_id', $userId)
            ->where('is_synced', false)
            ->get();
    }

    /**
     * Mark action synced
     */
    public function markAsSynced(SyncQueue $action)
    {
        $action->update([
            'is_synced' => true,
            'synced_at' => Carbon::now(),
        ]);
    }

    /**
     * Upload offline actions
     */
    public function uploadOfflineData(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'actions' => 'required|array'
        ]);

        foreach ($request->actions as $action) {
            $this->queueAction(
                $request->user_id,
                $action['action_type'],
                $action['payload']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Offline actions uploaded successfully'
        ]);
    }

    /**
     * MAIN SYNC ENGINE
     */
    public function sync(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'device_id' => 'required'
        ]);

        return DB::transaction(function () use ($request) {

            $device = Device::find($request->device_id);

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found'
                ], 404);
            }

            $pending = $this->pendingActions($request->user_id);

            $processedCount = 0;

            foreach ($pending as $action) {

                // SIMULATED processing (since forum module doesn't exist)
                // In real system: create posts/comments here

                $this->markAsSynced($action);
                $processedCount++;
            }

            // Create sync log
            SyncLog::create([
                'user_id' => $request->user_id,
                'device_id' => $device->id,
                'records_synced' => $processedCount,
                'status' => 'success',
                'synced_at' => Carbon::now()
            ]);

            // Update device sync time
            $device->update([
                'last_sync_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Synchronization completed successfully',
                'synced_records' => $processedCount
            ]);
        });
    }

    /**
     * Get pending data
     */
    public function getPendingData(Request $request)
    {
        $request->validate([
            'user_id' => 'required'
        ]);

        return response()->json([
            'success' => true,
            'pending_actions' => $this->pendingActions($request->user_id)
        ]);
    }
}
       