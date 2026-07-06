@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <h2 class="stats-page-title fly-in">Forum Statistics</h2>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Total Groups</p>
                <p class="stat-number">{{ $totalGroups }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">Total Topics</p>
                <p class="stat-number">{{ $totalTopics }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <p class="stat-label">Total Posts</p>
                <p class="stat-number">{{ $totalPosts }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i class="bi bi-person-fill"></i></div>
                <p class="stat-label">Total Users</p>
                <p class="stat-number">{{ $totalUsers }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card text-center fly-in fly-in-delay-2">
                <p class="stat-label">Posts Today</p>
                <p class="stat-number">{{ $postsToday }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center fly-in fly-in-delay-3">
                <p class="stat-label">Posts This Week</p>
                <p class="stat-number">{{ $postsThisWeek }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center fly-in fly-in-delay-4">
                <p class="stat-label">Posts This Month</p>
                <p class="stat-number">{{ $postsThisMonth }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-lg-6">
            <div class="card fly-in fly-in-delay-3">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 fw-semibold">Top 5 Active Users</h6>
                </div>
                <div class="card-body py-2">
                    @forelse($topUsers as $index => $user)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span>
                                @if($index == 0) 🥇
                                @elseif($index == 1) 🥈
                                @elseif($index == 2) 🥉
                                @else {{ $index + 1 }}.
                                @endif
                                <strong>{{ $user->name }}</strong>
                            </span>
                            <span class="badge bg-primary">{{ $user->posts_count }} Posts</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No user activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="row g-2">
                <div class="col-12">
                    <div class="card fly-in fly-in-delay-4">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Most Active User</h6></div>
                        <div class="card-body py-2">
                            @if($mostActiveUser)
                                <p class="mb-0"><strong>{{ $mostActiveUser->name }}</strong> — {{ $mostActiveUser->posts_count }} posts</p>
                            @else
                                <p class="text-muted small mb-0">No data available.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card fly-in fly-in-delay-5">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Most Active Group</h6></div>
                        <div class="card-body py-2">
                            @if($mostActiveGroup)
                                <p class="mb-0"><strong>{{ $mostActiveGroup->Group_Name }}</strong> — {{ $mostActiveGroup->topics_count }} topics</p>
                            @else
                                <p class="text-muted small mb-0">No data available.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card fly-in fly-in-delay-6">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Most Active Topic</h6></div>
                        <div class="card-body py-2">
                            @if($mostActiveTopic)
                                <p class="mb-0"><strong>{{ $mostActiveTopic->Title }}</strong> — {{ $mostActiveTopic->posts_count }} posts</p>
                            @else
                                <p class="text-muted small mb-0">No data available.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3">
        <div class="col-lg-6">
            <div class="card stats-chart-card fly-in fly-in-delay-4">
                <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Posts per Group</h6></div>
                <div class="card-body"><canvas id="postsChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card stats-chart-card fly-in fly-in-delay-5">
                <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Posts Per Month</h6></div>
                <div class="card-body"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card stats-chart-card fly-in fly-in-delay-6">
                <div class="card-header bg-white py-2"><h6 class="mb-0 fw-semibold">Topics by Group</h6></div>
                <div class="card-body"><canvas id="pieChart"></canvas></div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartColor = '#16a34a';

new Chart(document.getElementById('postsChart'), {
    type: 'bar',
    data: {
        labels: @json($groupLabels),
        datasets: [{
            label: 'Posts',
            data: @json($groupPosts),
            backgroundColor: chartColor,
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: @json($monthLabels),
        datasets: [{
            label: 'Posts',
            data: @json($monthlyPosts),
            borderColor: chartColor,
            backgroundColor: 'rgba(22,163,74,0.1)',
            fill: true,
            tension: 0.3,
            borderWidth: 2
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
        labels: @json($topicLabels),
        datasets: [{
            data: @json($topicCounts),
            backgroundColor: ['#166534', '#16a34a', '#4ade80', '#86efac', '#bbf7d0', '#dcfce7']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>
@endpush
