<?php

namespace App\Http\Controllers;

use App\Services\SyncService;
use App\Models\User;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(protected SyncService $syncService) {}

    protected function setRequestUser(Request $request, int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'device_id' => 'required|string',
            'device_name' => 'required|string',
        ]);

        $this->setRequestUser($request, $request->input('user_id'));

        return $this->syncService->registerDevice($request);
    }

    public function uploadOfflineData(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'actions' => 'required|array',
            'actions.*.action_type' => 'required|string',
            'actions.*.payload' => 'nullable',
        ]);

        $this->setRequestUser($request, $request->input('user_id'));

        return $this->syncService->uploadOfflineData($request);
    }

    public function sync(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'device_id' => 'required',
        ]);

        $this->setRequestUser($request, $request->input('user_id'));

        return $this->syncService->sync($request);
    }

    public function getPendingData(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $this->setRequestUser($request, $request->input('user_id'));

        return $this->syncService->getPendingData($request);
    }
}