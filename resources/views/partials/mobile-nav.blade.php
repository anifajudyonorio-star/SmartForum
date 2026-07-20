@php
    $user = auth()->user();
    $items = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Home', 'icon' => 'bi-house-fill'],
        ['route' => 'groups.index', 'match' => 'groups.*', 'label' => 'Groups', 'icon' => 'bi-people-fill'],
        ['route' => 'topics.search', 'match' => 'topics.*', 'label' => 'Topics', 'icon' => 'bi-search'],
        ['route' => 'notifications.index', 'match' => 'notifications.*', 'label' => 'Alerts', 'icon' => 'bi-bell-fill'],
    ];
    if ($user && $user->isStudent()) {
        $items[] = ['route' => 'student.quizzes', 'match' => 'student.*', 'label' => 'Quiz', 'icon' => 'bi-patch-question-fill'];
    } elseif ($user && ($user->isLecturer() || $user->isAdmin())) {
        $items[] = ['route' => 'quizzes.index', 'match' => 'quizzes.*|quiz-categories.*|questions.*|category-enrollments.*', 'label' => 'Quiz', 'icon' => 'bi-patch-question-fill'];
    }
@endphp

<nav class="mobile-bottom-nav d-md-none" aria-label="Mobile navigation">
    @foreach($items as $item)
        @php
            $patterns = explode('|', $item['match']);
            $active = collect($patterns)->contains(fn ($p) => request()->routeIs($p));
        @endphp
        <a href="{{ route($item['route']) }}" class="mobile-nav-item {{ $active ? 'active' : '' }}">
            <span class="mobile-nav-icon-wrap">
                <i class="bi {{ $item['icon'] }}"></i>
                @if($item['route'] === 'notifications.index')
                    <span class="notif-badge notif-badge-mobile {{ ($unreadNotificationCount ?? 0) > 0 ? '' : 'd-none' }}"
                          data-notif-badge>{{ ($unreadNotificationCount ?? 0) > 99 ? '99+' : ($unreadNotificationCount ?? 0) }}</span>
                @endif
            </span>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
