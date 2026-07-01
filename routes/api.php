<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyncController;

Route::prefix('sync')->group(function () {

    Route::post('/upload', [SyncController::class, 'uploadOfflineData']);

    Route::post('/', [SyncController::class, 'sync']);

    Route::get('/pending', [SyncController::class, 'getPendingData']);

});