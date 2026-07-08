<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer(['layouts.app', 'partials.sidebar', 'partials.mobile-nav'], function ($view) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            $view->with('unreadNotificationCount', $user->notifications()->where('Is_Read', false)->count());
            $view->with('latestNotificationId', (int) $user->notifications()->max('id'));
        });
    }
}
