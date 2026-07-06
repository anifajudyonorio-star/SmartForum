@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="mb-3 fly-in">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h2 class="fw-bold mb-1">Admin Dashboard</h2>
                <p class="text-muted small mb-0">Manage the forum, review usage statistics, and keep the community healthy.</p>
            </div>
            <a href="{{ route('statistics.index') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-graph-up-arrow me-1"></i> View Statistics
            </a>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-person-fill"></i></div>
                <p class="stat-label">Total Users</p>
                <p class="stat-number">{{ $totalUsers }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Total Groups</p>
                <p class="stat-number">{{ $totalGroups }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">Total Topics</p>
                <p class="stat-number">{{ $totalTopics }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <p class="stat-label">Total Posts</p>
                <p class="stat-number">{{ $totalPosts }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3">
        <div class="col-lg-6">
            <div class="card h-100 fly-in fly-in-delay-5">
                <div class="card-header bg-white py-2 border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy-fill me-1 text-primary"></i>Top Groups by Topics</h6>
                </div>
                <div class="card-body py-2">
                    <ul class="list-group list-group-flush">
                        @forelse($topGroups as $group)
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <span class="small">{{ $group->Group_Name }}</span>
                                <span class="badge bg-primary">{{ $group->topics_count }}</span>
                            </li>
                        @empty
                            <li class="list-group-item border-0 px-0 text-muted small">No groups found.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 fly-in fly-in-delay-6">
                <div class="card-header bg-white py-2 border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-fire me-1 text-primary"></i>Top Topics by Posts</h6>
                </div>
                <div class="card-body py-2">
                    <ul class="list-group list-group-flush">
                        @forelse($topTopics as $topic)
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <span class="small text-truncate me-2">{{ $topic->Title }}</span>
                                <span class="badge" style="background:var(--primary-muted);color:var(--primary-dark);">{{ $topic->posts_count }}</span>
                            </li>
                        @empty
                            <li class="list-group-item border-0 px-0 text-muted small">No topics found.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
