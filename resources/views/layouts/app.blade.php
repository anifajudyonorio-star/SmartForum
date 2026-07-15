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
                        <button id="sidebarToggle" class="btn btn-outline-success btn-sm d-md-none me-2" type="button" aria-label="Toggle sidebar">
                            <i class="bi bi-list"></i>
                        </button>
                    @endauth

                    <button id="backBtn" onclick="history.back()" class="btn btn-sm btn-outline-secondary me-2 d-none" title="Go back" aria-label="Go back">
                        <i class="bi bi-arrow-left"></i>
                    </button>

                    <a class="navbar-brand" href="{{ url('/') }}">
                        Smart Discussion
                    </a>

                    @auth
                        <button id="networkToggleBtn" class="btn btn-sm btn-outline-success ms-auto me-2" title="Toggle network" onclick="window._toggleNetwork()">
                            <i class="bi bi-wifi" id="networkToggleIcon"></i>
                            <span class="d-none d-md-inline ms-1" id="networkToggleText">Online</span>
                        </button>
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
                                        <a class="dropdown-item py-2" href="{{ route('profile.edit') }}">
                                            <i class="bi bi-person-circle me-2"></i>{{ __('Profile') }}
                                        </a>
                                        <a class="dropdown-item py-2" href="{{ route('logout') }}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}
                                        </a>

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
        <div id="offlineBanner" class="offline-banner" role="alert" aria-live="assertive"></div>
    @endauth

    @stack('scripts')
    <script>
        if (window.history.length > 1) {
            document.getElementById('backBtn')?.classList.remove('d-none');
        }
    </script>
</body>
</html>
