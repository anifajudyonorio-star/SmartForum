<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\GroupApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\ParticipationApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\StatisticsApiController;
use App\Http\Controllers\Api\TopicApiController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\RecommendationController;

// Desktop client auth
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/register', [AuthApiController::class, 'register']);

// Issue a Sanctum token for the authenticated web user (offline sync)
Route::middleware('auth')->post('/token', function (\Illuminate\Http\Request $request) {
    $token = $request->user()->createToken('offline-sync')->plainTextToken;

    return response()->json(['token' => $token]);
});

// Authenticated user info (used to verify Google OAuth token from desktop)
Route::middleware('auth:sanctum')->get('/user', function (\Illuminate\Http\Request $request) {
    $user = $request->user();

    return response()->json([
        'user' => [
            'id' => $user->id,
            'Fname' => $user->Fname,
            'Lname' => $user->Lname,
            'email' => $user->email,
            'role' => $user->role,
            'can_view_statistics' => $user->canViewStatistics(),
            'can_view_participation' => $user->canViewParticipation(),
            'administers_groups' => $user->administeredGroups()->exists(),
            'administered_groups_count' => $user->administeredGroups()->count(),
        ],
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardApiController::class, 'show']);
    Route::get('/statistics', [StatisticsApiController::class, 'index']);
    Route::get('/statistics/groups/{group}', [StatisticsApiController::class, 'show']);
    Route::get('/participation', [ParticipationApiController::class, 'index']);

    Route::get('/groups/explore', [GroupApiController::class, 'explore']);
    Route::post('/groups/{group}/join', [GroupApiController::class, 'requestJoin']);
    Route::post('/groups/{group}/join-requests/{user}/approve', [GroupApiController::class, 'approveJoinRequest']);
    Route::post('/groups/{group}/join-requests/{user}/reject', [GroupApiController::class, 'rejectJoinRequest']);

    Route::get('/groups', [GroupApiController::class, 'index']);
    Route::post('/groups', [GroupApiController::class, 'store']);
    Route::get('/groups/{group}', [GroupApiController::class, 'show']);
    Route::post('/groups/{group}/members', [GroupApiController::class, 'addMember']);
    Route::delete('/groups/{group}/members/{user}', [GroupApiController::class, 'removeMember']);
    Route::patch('/groups/{group}/members/{user}/role', [GroupApiController::class, 'updateMemberRole']);

    Route::get('/topics', [TopicApiController::class, 'index']);
    Route::get('/topics/search', [TopicApiController::class, 'search']);
    Route::get('/topics/{topic}', [TopicController::class, 'apiShow']);
    Route::put('/topics/{topic}', [TopicApiController::class, 'update']);
    Route::delete('/topics/{topic}', [TopicApiController::class, 'destroy']);
    Route::get('/groups/{group}/topics', [TopicApiController::class, 'forGroup']);
    Route::post('/groups/{group}/topics', [TopicApiController::class, 'store']);
    Route::post('/topics/{topic}/view', [TopicApiController::class, 'view']);

    Route::post('/topics/{topic}/posts', [PostApiController::class, 'store']);
    Route::put('/posts/{post}', [PostApiController::class, 'update']);
    Route::delete('/posts/{post}', [PostApiController::class, 'destroy']);
    Route::post('/posts/{post}/report', [ReportApiController::class, 'store']);

    Route::get('/groups/{group}/post-reports', [ReportApiController::class, 'index']);
    Route::post('/groups/{group}/post-reports/{report}/restore', [ReportApiController::class, 'restore']);
    Route::delete('/groups/{group}/post-reports/{report}', [ReportApiController::class, 'destroy']);

    Route::get('/notifications', [NotificationApiController::class, 'index']);
    Route::get('/notifications/poll', [NotificationApiController::class, 'poll']);
    Route::patch('/notifications/{id}/read', [NotificationApiController::class, 'markAsRead']);

    Route::get('/recommendations', [RecommendationController::class, 'index']);

    Route::patch('/profile', [ProfileApiController::class, 'update']);
    Route::put('/profile/password', [ProfileApiController::class, 'updatePassword']);
    Route::delete('/profile', [ProfileApiController::class, 'destroy']);
});

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
