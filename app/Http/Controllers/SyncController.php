<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SyncQueue;
use App\Models\Device;
use App\Services\SyncService;

class SyncController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Receive offline data from a device.
     */
    public function uploadOfflineData(Request $request)
    {
        return $this->syncService->uploadOfflineData($request);
    }

    /**
     * Synchronize pending offline data.
     */
    public function sync(Request $request)
    {
        return $this->syncService->sync($request);
    }

    /**
     * Return data that has not yet been synchronized.
     */
    public function getPendingData(Request $request)
    {
        return $this->syncService->getPendingData($request);
    }
}
