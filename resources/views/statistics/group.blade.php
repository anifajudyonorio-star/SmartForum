@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <a href="{{ route('statistics.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i> All Statistics
        </a>
        <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary btn-sm mb-2 ms-1">
            <i class="bi bi-people me-1"></i> Open Group
        </a>
        <h1 class="page-title">{{ $group->Group_Name }}</h1>
        <p class="page-subtitle">
            Group statistics
            @if($group->user)
                &bull; Created by: {{ $group->user->name }}
            @endif
            &bull; <span class="badge bg-primary">{{ $group->Status }}</span>
        </p>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Members</p>
                <p class="stat-number">{{ $members_count }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">Topics</p>
                <p class="stat-number">{{ $topics_count }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <p class="stat-label">Posts</p>
                <p class="stat-number">{{ $posts_count }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-calendar-day"></i></div>
                <p class="stat-label">Posts Today</p>
                <p class="stat-number">{{ $posts_today }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card text-center fly-in">
                <p class="stat-label">Posts This Week</p>
                <p class="stat-number">{{ $posts_this_week }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center fly-in fly-in-delay-1">
                <p class="stat-label">Posts This Month</p>
                <p class="stat-number">{{ $posts_this_month }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center fly-in fly-in-delay-2">
                <p class="stat-label">Avg Posts / Topic</p>
                <p class="stat-number">{{ $topics_count > 0 ? round($posts_count / $topics_count, 1) : 0 }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-lg-6">
            <div class="card fly-in">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-semibold">Top Active Members</h6>
                </div>
                <div class="card-body py-2">
                    @forelse($top_users as $index => $user)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span>
                                @if($index == 0) 🥇
                                @elseif($index == 1) 🥈
                                @elseif($index == 2) 🥉
                                @else {{ $index + 1 }}.
                                @endif
                                <strong>{{ $user->name }}</strong>
                            </span>
                            <span class="badge bg-primary">{{ $user->posts_count }} posts</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No posts in this group yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="row g-2">
                <div class="col-12">
                    <div class="card fly-in fly-in-delay-1">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Most Active Member</h6></div>
                        <div class="card-body py-2">
                            @if($most_active_user)
                                <p class="mb-0"><strong>{{ $most_active_user->name }}</strong> — {{ $most_active_user->posts_count }} posts</p>
                            @else
                                <p class="text-muted small mb-0">No activity yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card fly-in fly-in-delay-2">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Most Active Topic</h6></div>
                        <div class="card-body py-2">
                            @if($most_active_topic)
                                <p class="mb-0">
                                    <strong>{{ $most_active_topic->Title }}</strong>
                                    — {{ $most_active_topic->posts_count }} posts
                                </p>
                            @else
                                <p class="text-muted small mb-0">No topics yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-lg-6">
            <div class="card stats-chart-card fly-in">
                <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Posts Per Month (This Group)</h6></div>
                <div class="card-body"><canvas id="groupMonthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card stats-chart-card fly-in fly-in-delay-1">
                <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Posts by Topic</h6></div>
                <div class="card-body"><canvas id="groupTopicsChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="card fly-in">
        <div class="card-header bg-white py-2">
            <h6 class="mb-0 fw-semibold">All Topics in This Group</h6>
        </div>
        <div class="card-body p-0">
            <div class="responsive-table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 dashboard-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Topic</th>
                                <th>Posts</th>
                                <th class="pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topics as $topic)
                                <tr>
                                    <td class="ps-3">{{ $topic->Title }}</td>
                                    <td><span class="badge bg-primary">{{ $topic->posts_count }}</span></td>
                                    <td class="pe-3">
                                        <a href="{{ route('topics.show', $topic) }}" class="btn btn-outline-primary btn-sm">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No topics in this group.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="responsive-card-wrap p-2">
                <div class="data-card-list">
                    @forelse($topics as $topic)
                        <div class="data-card-item">
                            <p class="data-card-item-title">{{ $topic->Title }}</p>
                            <div class="data-card-item-meta">
                                <span><i class="bi bi-chat me-1"></i>{{ $topic->posts_count }} posts</span>
                            </div>
                            <div class="data-card-item-actions">
                                <a href="{{ route('topics.show', $topic) }}" class="btn btn-outline-primary btn-sm w-100">Open</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small text-center py-3 mb-0">No topics in this group.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartColor = themeColor('primary');

new Chart(document.getElementById('groupMonthlyChart'), {
    type: 'line',
    data: {
        labels: @json($month_labels),
        datasets: [{
            label: 'Posts',
            data: @json($monthly_posts),
            borderColor: chartColor,
            backgroundColor: themeColorAlpha('primary', 0.1),
            fill: true,
            tension: 0.3,
            borderWidth: 2
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});

@if(count($topic_labels) > 0)
new Chart(document.getElementById('groupTopicsChart'), {
    type: 'bar',
    data: {
        labels: @json($topic_labels),
        datasets: [{
            label: 'Posts',
            data: @json($topic_post_counts),
            backgroundColor: chartColor,
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
@endif
</script>
@endpush
