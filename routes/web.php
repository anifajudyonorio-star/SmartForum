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
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizCategoryController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentQuizController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
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
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::match(['get', 'patch'], '/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('/statistics', [StatisticsController::class, 'index'])
        ->middleware('role:admin')
        ->name('statistics.index');
    Route::get('/statistics/groups/{group}', [StatisticsController::class, 'group'])
        ->middleware('role:admin')
        ->name('statistics.group');

    Route::get('/participation', [ParticipationController::class, 'index'])
        ->middleware('role:lecturer')
        ->name('participation.index');

    // Student quizzes
    Route::get('/student/quizzes', [StudentQuizController::class, 'index'])->name('student.quizzes');
    Route::get('/student/quizzes/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.show');
    Route::post('/student/quizzes/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');

    // Lecturer / admin quiz management
    Route::middleware('role:lecturer')->group(function () {
        Route::resource('quiz-categories', QuizCategoryController::class)->except(['show']);
        Route::resource('quizzes', QuizController::class)->except(['show']);
        Route::patch('/quizzes/{quiz}/publish', [QuizController::class, 'publish'])->name('quizzes.publish');
        Route::resource('questions', QuestionController::class)->except(['show']);
    });

    //Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

    //Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    //Route::post('/posts/{post}/reply', [PostController::class, 'storeReply'])->name('posts.storeReply');
});
require __DIR__.'/auth.php';

