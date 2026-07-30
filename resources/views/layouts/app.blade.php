<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
@php
use Illuminate\Support\Facades\Auth;
@endphp

<meta name="csrf-token" content="{{ csrf_token() }}">

@if(Auth::check())
<script>
    window.currentUserId = {{ auth()->id() }};
</script>
@endif

@auth
    <meta name="notifications-poll-url" content="{{ route('notifications.poll') }}">
    <meta name="notifications-last-id" content="{{ $latestNotificationId ?? 0 }}">
    <meta name="notifications-unread" content="{{ $unreadNotificationCount ?? 0 }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
@endauth

<title>Smart Discussion</title>

<link rel="dns-prefetch" href="//fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Nunito:400,500,600,700" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

@vite(['resources/sass/app.scss', 'resources/js/app.js'])
@stack('styles')
</head>
<body class="@yield('body-class')">
    <div id="app" class="app-shell">
        @auth
            @include('partials.sidebar')
        @endauth

        <div class="app-main">
            <nav class="navbar navbar-expand-md app-navbar">
                <div class="container-fluid px-3 px-md-4">
                    @auth
                        <button id="sidebarToggle" class="btn btn-outline-secondary btn-sm d-md-none me-2" type="button" aria-label="Toggle sidebar">
                            <i class="bi bi-list"></i>
                        </button>
                    @endauth

                    <button id="backBtn" onclick="history.back()" class="btn btn-outline-secondary btn-sm me-2 d-none" title="Go back" aria-label="Go back">
                        <i class="bi bi-arrow-left"></i>
                    </button>

                    <a class="navbar-brand" href="{{ url('/') }}">
                        Smart Discussion
                    </a>

                    @auth
                    @endauth

                    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav me-auto"></ul>

                        <ul class="navbar-nav ms-auto">
                            @auth
                            @endauth
                            @guest
                                @if (Route::has('login'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                    </li>
                                @endif

                                @if (Route::has('register'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                    </li>
                                @endif
                            @else
                                <li class="nav-item dropdown">
                                    <a id="navbarDropdown" class="nav-link dropdown-toggle user-profile-trigger" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                        <span class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                                        <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
                                    </a>

                                    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="navbarDropdown">
                                        <div class="px-3 py-2 border-bottom">
                                            <div class="fw-semibold">{{ Auth::user()->name }}</div>
                                            <small class="text-muted d-block">{{ Auth::user()->email }}</small>
                                            <small class="badge mt-1" style="background:var(--primary-muted);color:var(--primary-dark);">
                                                @if(Auth::user()->isAdmin()) Super Admin
                                                @elseif(Auth::user()->isLecturer()) Lecturer
                                                @else Student
                                                @endif
                                            </small>
                                        </div>
                                        <div class="dropdown-menu-actions">
                                        <a class="dropdown-item dropdown-item-action {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                                           href="{{ route('profile.edit') }}">
                                            <i class="bi bi-person-circle"></i>
                                            <span>{{ __('Profile') }}</span>
                                        </a>
                                        <a class="dropdown-item dropdown-item-action"
                                           href="{{ route('logout') }}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="bi bi-box-arrow-right"></i>
                                            <span>{{ __('Logout') }}</span>
                                        </a>
                                        </div>

                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </div>
                                </li>
                            @endguest
                        </ul>
                    </div>
                </div>
            </nav>

            @auth
                @include('partials.offline-sync')
            @endauth

            <main class="app-content">
                @yield('content')
            </main>

            @auth
                @include('partials.mobile-nav')
            @endauth
        </div>
    </div>

    @auth
        <div id="notifToastStack" class="wa-notif-stack" aria-live="polite"></div>
        @include('partials.quiz-launch-modal')
    @endauth

    {{-- Inline charts call themeColor before the Vite module finishes loading --}}
    <script>
        (function () {
            if (typeof window.themeColor === 'function') {
                return;
            }

            function themeColor(name) {
                return getComputedStyle(document.documentElement).getPropertyValue('--' + name).trim();
            }

            function themeColorAlpha(name, alpha) {
                const hex = themeColor(name).replace('#', '');
                if (hex.length !== 6) {
                    return themeColor(name);
                }
                const r = parseInt(hex.slice(0, 2), 16);
                const g = parseInt(hex.slice(2, 4), 16);
                const b = parseInt(hex.slice(4, 6), 16);
                return 'rgba(' + r + ', ' + g + ', ' + alpha + ')';
            }

            function themeChartPalette() {
                return [
                    themeColor('sidebar-bg'),
                    themeColor('primary'),
                    themeColor('primary-light'),
                    themeColor('primary-lighter'),
                    themeColor('primary-soft'),
                    themeColor('primary-muted'),
                ];
            }

            window.themeColor = themeColor;
            window.themeColorAlpha = themeColorAlpha;
            window.themeChartPalette = themeChartPalette;
        })();
    </script>

    @stack('scripts')
    <script>
        if (window.history.length > 1) {
            document.getElementById('backBtn')?.classList.remove('d-none');
        }
    </script>
</body>
</html>
