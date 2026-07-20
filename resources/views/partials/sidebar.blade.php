@php
    $user = auth()->user();
    $navItems = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
        ['route' => 'groups.index', 'match' => 'groups.*', 'label' => 'Groups', 'icon' => 'bi-people-fill'],
        ['route' => 'topics.search', 'match' => 'topics.*', 'label' => 'Search Topics', 'icon' => 'bi-search'],
        ['route' => 'notifications.index', 'match' => 'notifications.*', 'label' => 'Notifications', 'icon' => 'bi-bell-fill'],
    ];

    if ($user && $user->isStudent()) {
        $navItems[] = ['route' => 'student.quizzes', 'match' => 'student.quizzes', 'label' => 'Quizzes', 'icon' => 'bi-patch-question-fill'];
        $navItems[] = ['route' => 'student.announcements', 'match' => 'student.announcements', 'label' => 'Announcements', 'icon' => 'bi-megaphone-fill'];
        $navItems[] = ['route' => 'student.quizzes.progress', 'match' => 'student.quizzes.progress', 'label' => 'Quiz Progress', 'icon' => 'bi-graph-up-arrow'];
    }

    $showGroupAdminTools = $user && $user->canViewStatistics();
    $showParticipation = $user && $user->canViewParticipation();
    $showLecturerTools = $user && ($user->isLecturer() || $user->isAdmin());
@endphp

{{-- Desktop sidebar (fixed) --}}
<aside class="app-sidebar d-none d-md-block">
    <div class="sidebar-inner">
        <div class="sidebar-brand">
            <p class="sidebar-brand-title">Smart Discussion</p>
            <p class="sidebar-brand-sub">Learning forum</p>
        </div>

        <ul class="sidebar-nav">
            @foreach($navItems as $item)
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
                       href="{{ route($item['route']) }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if($item['route'] === 'notifications.index')
                            <span class="notif-badge {{ ($unreadNotificationCount ?? 0) > 0 ? '' : 'd-none' }}"
                                  data-notif-badge>{{ ($unreadNotificationCount ?? 0) > 99 ? '99+' : ($unreadNotificationCount ?? 0) }}</span>
                        @endif
                    </a>
                </li>
            @endforeach

            @if($showGroupAdminTools || $showParticipation)
                <li class="sidebar-section-label">Group Admin</li>
                @if($showGroupAdminTools)
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('statistics.*') ? 'active' : '' }}"
                           href="{{ route('statistics.index') }}">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Statistics</span>
                        </a>
                    </li>
                @endif
                @if($showParticipation)
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('participation.*') ? 'active' : '' }}"
                           href="{{ route('participation.index') }}">
                            <i class="bi bi-bar-chart-fill"></i>
                            <span>Participation</span>
                        </a>
                    </li>
                @endif
            @endif

            @if($showLecturerTools)
                <li class="sidebar-section-label">Lecturer</li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('quizzes.*') || request()->routeIs('quiz-categories.*') || request()->routeIs('questions.*') || request()->routeIs('category-enrollments.*') || request()->routeIs('quiz-announcements.*') ? 'active' : '' }}"
                       href="{{ route('quizzes.index') }}">
                        <i class="bi bi-patch-question-fill"></i>
                        <span>Quizzes</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                       href="{{ route('reports.index') }}">
                        <i class="bi bi-graph-up"></i>
                        <span>Quiz Reports</span>
                    </a>
                </li>
            @endif

            @if($user && $user->isAdmin())
                <li class="sidebar-section-label">Super Admin</li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}"
                       href="{{ route('admin.users') }}">
                        <i class="bi bi-people"></i>
                        <span>User Management</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
</aside>

{{-- Mobile sidebar (off-canvas) --}}
<div id="mobileSidebarBackdrop" class="d-md-none"></div>

<aside id="mobileSidebar" class="app-sidebar d-md-none">
    <div class="sidebar-inner">
        <div class="sidebar-brand d-flex justify-content-between align-items-start">
            <div>
                <p class="sidebar-brand-title">Smart Discussion</p>
                <p class="sidebar-brand-sub">Learning forum</p>
            </div>
            <button id="mobileSidebarClose" class="btn btn-sm btn-link text-white p-0 opacity-75" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <ul class="sidebar-nav">
            @foreach($navItems as $item)
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
                       href="{{ route($item['route']) }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if($item['route'] === 'notifications.index')
                            <span class="notif-badge {{ ($unreadNotificationCount ?? 0) > 0 ? '' : 'd-none' }}"
                                  data-notif-badge>{{ ($unreadNotificationCount ?? 0) > 99 ? '99+' : ($unreadNotificationCount ?? 0) }}</span>
                        @endif
                    </a>
                </li>
            @endforeach

            @if($showGroupAdminTools || $showParticipation)
                <li class="sidebar-section-label">Group Admin</li>
                @if($showGroupAdminTools)
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('statistics.*') ? 'active' : '' }}"
                           href="{{ route('statistics.index') }}">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Statistics</span>
                        </a>
                    </li>
                @endif
                @if($showParticipation)
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link {{ request()->routeIs('participation.*') ? 'active' : '' }}"
                           href="{{ route('participation.index') }}">
                            <i class="bi bi-bar-chart-fill"></i>
                            <span>Participation</span>
                        </a>
                    </li>
                @endif
            @endif

            @if($showLecturerTools)
                <li class="sidebar-section-label">Lecturer</li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('quizzes.*') || request()->routeIs('quiz-categories.*') || request()->routeIs('questions.*') || request()->routeIs('category-enrollments.*') || request()->routeIs('quiz-announcements.*') ? 'active' : '' }}"
                       href="{{ route('quizzes.index') }}">
                        <i class="bi bi-patch-question-fill"></i>
                        <span>Quizzes</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                       href="{{ route('reports.index') }}">
                        <i class="bi bi-graph-up"></i>
                        <span>Quiz Reports</span>
                    </a>
                </li>
            @endif

            @if($user && $user->isAdmin())
                <li class="sidebar-section-label">Super Admin</li>
                <li class="sidebar-nav-item">
                    <a class="sidebar-nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}"
                       href="{{ route('admin.users') }}">
                        <i class="bi bi-people"></i>
                        <span>User Management</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
</aside>
