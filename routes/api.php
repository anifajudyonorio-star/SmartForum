<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\RecommendationController;

// Issue a Sanctum token for the authenticated web user
Route::middleware('auth')->post('/token', function (\Illuminate\Http\Request $request) {
    $token = $request->user()->createToken('offline-sync')->plainTextToken;
    return response()->json(['token' => $token]);
});
// Desktop client auth
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/register', [AuthApiController::class, 'register']);

// Authenticated user info (used to verify Google OAuth token from desktop)
Route::middleware('auth:sanctum')->get('/user', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    return response()->json([
        'user' => [
            'id'    => $user->id,
            'Fname' => $user->Fname,
            'Lname' => $user->Lname,
            'email' => $user->email,
            'role'  => $user->role,
        ],
    ]);
});

Route::middleware('auth:sanctum')->get('/topics/{topic}', [TopicController::class, 'apiShow']);

Route::prefix('push')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/subscribe', [PushController::class, 'subscribe']);
    Route::delete('/unsubscribe', [PushController::class, 'unsubscribe']);
});

Route::prefix('sync')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/device', [SyncController::class, 'registerDevice']);
    Route::post('/upload', [SyncController::class, 'uploadOfflineData']);
    Route::post('/', [SyncController::class, 'sync']);
    Route::get('/pending', [SyncController::class, 'getPendingData']);
    Route::get('/status', [SyncController::class, 'status']);

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/recommendations', [RecommendationController::class, 'index']);
});