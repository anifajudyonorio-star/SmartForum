<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupModerationController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizCategoryController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\PerformanceReportController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google|apple')
    ->name('auth.social.redirect');

Route::post('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|apple')
    ->name('auth.social.callback');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|apple');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Group member management — authorized in controller (group admins + system admins).
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
    Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');
    Route::patch('/groups/{group}/members/{user}/role', [GroupController::class, 'updateMemberRole'])->name('groups.members.role');

    // Group-scoped moderation (warn / suspend / block / reinstate).
    Route::post('/groups/{group}/members/{user}/warn', [GroupModerationController::class, 'warn'])->name('groups.members.warn');
    Route::post('/groups/{group}/members/{user}/suspend', [GroupModerationController::class, 'suspend'])->name('groups.members.suspend');
    Route::post('/groups/{group}/members/{user}/block', [GroupModerationController::class, 'block'])->name('groups.members.block');
    Route::post('/groups/{group}/members/{user}/reinstate', [GroupModerationController::class, 'reinstate'])->name('groups.members.reinstate');

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

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::post('/admin/users/{user}/warn', [AdminUserController::class, 'warn'])->name('admin.users.warn');
        Route::post('/admin/users/{user}/promote', [AdminUserController::class, 'promote'])->name('admin.users.promote');
        Route::post('/admin/users/{user}/blacklist', [AdminUserController::class, 'blacklist'])->name('admin.users.blacklist');
        Route::post('/admin/users/{user}/unblacklist', [AdminUserController::class, 'unblacklist'])->name('admin.users.unblacklist');
    });

    // Statistics & participation — authorized in controllers (system admin or group admin).
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('/statistics/groups/{group}', [StatisticsController::class, 'group'])->name('statistics.group');

    Route::get('/participation', [ParticipationController::class, 'index'])->name('participation.index');
    Route::get('/groups/{group}/participation', [ParticipationController::class, 'group'])->name('participation.group');

    // Student quizzes
    Route::get('/student/quizzes', [StudentQuizController::class, 'index'])->name('student.quizzes');
    Route::get('/student/quizzes/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.show');
    Route::post('/student/quizzes/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');

    // Lecturer / admin quiz management
    Route::middleware('role:lecturer')->group(function () {

    Route::resource('quiz-categories', QuizCategoryController::class)->except(['show']);

    Route::resource('quizzes', QuizController::class)->except(['show']);

    Route::patch('/quizzes/{quiz}/publish', [QuizController::class, 'publish'])
        ->name('quizzes.publish');

    Route::resource('questions', QuestionController::class)->except(['show']);

    Route::get('/reports', [PerformanceReportController::class, 'index'])
        ->name('reports.index');

});

    //Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

    //Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    //Route::post('/posts/{post}/reply', [PostController::class, 'storeReply'])->name('posts.storeReply');
});
require __DIR__.'/auth.php';

