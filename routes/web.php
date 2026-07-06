<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::resource('groups', GroupController::class);

    Route::get('/groups/{group}/topics/create', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/groups/{group}/topics', [TopicController::class, 'store'])->name('topics.store');

    Route::get('/topics/search', [TopicController::class, 'search'])->name('topics.search');
    Route::get('/topics/{topic}', [TopicController::class, 'show'])->name('topics.show');
    Route::get('/topics/{topic}/edit', [TopicController::class, 'edit'])->name('topics.edit');
    Route::put('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
    Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

    Route::post('/topics/{topic}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('/statistics', [StatisticsController::class, 'index'])
        ->middleware('role:admin')
        ->name('statistics.index');

    Route::get('/participation', [ParticipationController::class, 'index'])
        ->middleware('role:lecturer')
        ->name('participation.index');
    //Route::get('/topics/{topic}/posts/create', [PostController::class, 'create'])->name('posts.create');

    //Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

    //Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    //Route::post('/posts/{post}/reply', [PostController::class, 'storeReply'])->name('posts.storeReply');
});
require __DIR__.'/auth.php';

