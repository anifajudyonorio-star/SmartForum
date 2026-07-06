@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="dashboard-header fly-in">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="dashboard-title mb-0">
                    <i data-feather="shield" class="feather-icon me-1"></i>
                    Admin Dashboard
                </h2>
                <p class="text-muted small mb-0">Manage the forum, review usage statistics, and keep the community healthy.</p>
            </div>
            <a href="{{ route('statistics.index') }}" class="btn btn-primary btn-sm dashboard-action-btn">
                <i data-feather="bar-chart-2" class="feather-icon-sm me-1"></i> View Statistics
            </a>
        </div>
    </div>

    <div class="row g-2 mb-2">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i data-feather="users" class="feather-icon-sm"></i></div>
                <p class="stat-label">Total Users</p>
                <p class="stat-number">{{ $totalUsers }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i data-feather="layers" class="feather-icon-sm"></i></div>
                <p class="stat-label">Total Groups</p>
                <p class="stat-number">{{ $totalGroups }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i data-feather="book-open" class="feather-icon-sm"></i></div>
                <p class="stat-label">Total Topics</p>
                <p class="stat-number">{{ $totalTopics }}</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i data-feather="message-square" class="feather-icon-sm"></i></div>
                <p class="stat-label">Total Posts</p>
                <p class="stat-number">{{ $totalPosts }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2">
        <div class="col-lg-6">
            <div class="card dashboard-card h-100 fly-in fly-in-delay-5">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i data-feather="award" class="feather-icon-sm me-1 text-primary"></i>Top Groups by Topics
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse($topGroups as $group)
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-1">
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
            <div class="card dashboard-card h-100 fly-in fly-in-delay-6">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i data-feather="trending-up" class="feather-icon-sm me-1 text-primary"></i>Top Topics by Posts
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse($topTopics as $topic)
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-1">
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>feather.replace();</script>
@endpush
