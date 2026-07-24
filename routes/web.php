<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CategoryEnrollmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PerformanceReportController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAnnouncementController;
use App\Http\Controllers\QuizCategoryController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/latest-posts', [DashboardController::class, 'latestPosts'])->name('dashboard.latest-posts');

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

    Route::get('/groups/explore', [GroupController::class, 'explore'])->name('groups.explore');
    Route::post('/groups/{group}/join', [GroupController::class, 'requestJoin'])->name('groups.join');
    Route::post('/groups/{group}/join-requests/{user}/approve', [GroupController::class, 'approveJoinRequest'])->name('groups.join.approve');
    Route::post('/groups/{group}/join-requests/{user}/reject', [GroupController::class, 'rejectJoinRequest'])->name('groups.join.reject');

    Route::resource('groups', GroupController::class);

    Route::get('/groups/{group}/topics/create', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/groups/{group}/topics', [TopicController::class, 'store'])->name('topics.store');

    Route::get('/topics/search', [TopicController::class, 'search'])->name('topics.search');
    Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
    Route::get('/topics/{topic}', [TopicController::class, 'show'])->name('topics.show');
    Route::get('/topics/{topic}/posts-fragment', [TopicController::class, 'postsFragment'])->name('topics.posts-fragment');
    Route::get('/topics/{topic}/edit', [TopicController::class, 'edit'])->name('topics.edit');
    Route::put('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
    Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

    Route::post('/topics/{topic}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/report', [ReportController::class, 'store'])->name('posts.report');

    Route::post('/groups/{group}/post-reports/{report}/restore', [ReportController::class, 'restore'])
        ->name('groups.post-reports.restore');
    Route::delete('/groups/{group}/post-reports/{report}', [ReportController::class, 'destroy'])
        ->name('groups.post-reports.destroy');

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
    Route::middleware('role:student')->group(function () {
        Route::get('/student/quizzes', [StudentQuizController::class, 'index'])->name('student.quizzes');
        Route::post('/student/quizzes/enroll', [StudentQuizController::class, 'enroll'])
            ->name('student.quizzes.enroll');
        Route::post('/student/quizzes/unenroll', [StudentQuizController::class, 'unenroll'])
            ->name('student.quizzes.unenroll');
        Route::get('/student/quizzes/progress', [StudentQuizController::class, 'progress'])
            ->name('student.quizzes.progress');
        Route::get('/student/quizzes/launch-poll', [StudentQuizController::class, 'launchPoll'])
            ->name('student.quizzes.launch-poll');
        Route::get('/student/announcements', [QuizAnnouncementController::class, 'studentIndex'])
            ->name('student.announcements');
        Route::get('/student/quizzes/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.show');
        Route::post('/student/quizzes/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');
    });

    // Public quiz report for assigned members (visible after quiz ends)
    Route::get('/quizzes/{quiz}/report', [PerformanceReportController::class, 'publicQuiz'])
        ->middleware('auth')
        ->name('quizzes.report');

    // Lecturer / admin quiz management
    Route::middleware('role:lecturer')->group(function () {

        Route::resource('quiz-categories', QuizCategoryController::class)->except(['show']);

        Route::get('/category-enrollments', [CategoryEnrollmentController::class, 'index'])
            ->name('category-enrollments.index');
        Route::post('/category-enrollments', [CategoryEnrollmentController::class, 'store'])
            ->name('category-enrollments.store');
        Route::delete('/category-enrollments', [CategoryEnrollmentController::class, 'destroy'])
            ->name('category-enrollments.destroy');
        Route::post('/category-enrollments/lookup', [CategoryEnrollmentController::class, 'lookup'])
            ->name('category-enrollments.lookup');

        Route::get('/quiz-announcements', [QuizAnnouncementController::class, 'index'])
            ->name('quiz-announcements.index');
        Route::post('/quiz-announcements', [QuizAnnouncementController::class, 'store'])
            ->name('quiz-announcements.store');
        Route::delete('/quiz-announcements/{quiz_announcement}', [QuizAnnouncementController::class, 'destroy'])
            ->name('quiz-announcements.destroy');

        Route::resource('quizzes', QuizController::class)->except(['show']);
        Route::get('/quizzes/{quiz}/review', [QuizController::class, 'review'])
            ->name('quizzes.review');

        Route::patch('/quizzes/{quiz}/publish', [QuizController::class, 'publish'])
            ->name('quizzes.publish');

        Route::resource('questions', QuestionController::class)->except(['show']);

        Route::get('/reports', [PerformanceReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/quizzes/{quiz}', [PerformanceReportController::class, 'quiz'])
            ->name('reports.quiz');

    });

    // Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

    // Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    // Route::post('/posts/{post}/reply', [PostController::class, 'storeReply'])->name('posts.storeReply');
});
require __DIR__.'/auth.php';
