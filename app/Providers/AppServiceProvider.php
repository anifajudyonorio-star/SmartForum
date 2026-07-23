<?php

namespace App\Providers;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnnouncement;
use App\Models\QuizCategory;
use App\Policies\QuestionPolicy;
use App\Policies\QuizAnnouncementPolicy;
use App\Policies\QuizCategoryPolicy;
use App\Policies\QuizPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(QuizCategory::class, QuizCategoryPolicy::class);
        Gate::policy(QuizAnnouncement::class, QuizAnnouncementPolicy::class);

        Gate::define('admin-only', fn ($user) => $user->role === 'admin');

        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class.'@handle');

        View::composer(['layouts.app', 'partials.sidebar', 'partials.mobile-nav'], function ($view) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            $view->with('unreadNotificationCount', $user->notifications()->visible()->where('Is_Read', false)->count());
            $view->with('latestNotificationId', (int) $user->notifications()->visible()->max('id'));
        });
    }
}
