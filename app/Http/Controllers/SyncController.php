<?php

namespace App\Http\Controllers;

use App\Services\SyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(protected SyncService $syncService) {}

    public function registerDevice(Request $request)
    {
        return $this->syncService->registerDevice($request);
    }

    public function uploadOfflineData(Request $request)
    {
        return $this->syncService->uploadOfflineData($request);
    }

    public function sync(Request $request)
    {
        return $this->syncService->sync($request);
    }

    public function getPendingData(Request $request)
    {
        return $this->syncService->getPendingData($request);
    }

    public function status(Request $request)
    {
        return $this->syncService->status($request);
    }
}
