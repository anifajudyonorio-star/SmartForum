<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        Event::listen(SocialiteWasCalled::class, \SocialiteProviders\Apple\AppleExtendSocialite::class . '@handle');

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
