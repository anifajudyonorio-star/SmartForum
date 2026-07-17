@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="dashboard-header fly-in">
        <h2 class="dashboard-title mb-0">Welcome back, Student!</h2>
        <p class="text-muted small mb-0">Participate in discussions and stay connected with your classmates.</p>
    </div>

    @include('dashboard.partials.group-admin-groups')

    <div class="row g-2 mb-2">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <p class="stat-label">My Posts</p>
                <p class="stat-number">{{ $myPosts }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">My Topics</p>
                <p class="stat-number">{{ $myTopics }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-reply-fill"></i></div>
                <p class="stat-label">Replies</p>
                <p class="stat-number">{{ $myReplies }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Discussion Groups</p>
                <p class="stat-number">{{ $groups }}</p>
            </div>
        </div>
    </div>

    <div class="row g-2">
        <div class="col-lg-8">
            <div class="card dashboard-card fly-in fly-in-delay-3">
                <div class="card-header bg-white py-2 border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-primary"></i>Recent Topics</h6>
                </div>
                <div class="card-body py-2">
                    @forelse($recentTopics as $topic)
                        <div class="pb-2 mb-2 border-bottom">
                            <h6 class="mb-1 small">
                                <a href="{{ route('topics.show', $topic) }}" class="text-decoration-none text-dark">
                                    {{ $topic->Title }}
                                </a>
                            </h6>
                            <small class="text-muted">
                                {{ optional($topic->group)->Group_Name ?? 'No Group' }} &bull; {{ $topic->created_at->diffForHumans() }}
                            </small>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No recent topics.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dashboard-card mb-2 fly-in fly-in-delay-4">
                <div class="card-header bg-white py-2 border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-lightning-fill me-1 text-primary"></i>Quick Actions</h6>
                </div>
                <div class="card-body d-grid gap-2 py-2">
                    <a href="{{ route('groups.explore') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-compass me-1"></i> Explore Groups
                    </a>
                    <a href="{{ route('student.quizzes') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-patch-question me-1"></i> Take a Quiz
                    </a>
                </div>
            </div>

            <div class="card dashboard-card fly-in fly-in-delay-5">
                <div class="card-header bg-white py-2 border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-chat-left-text me-1 text-primary"></i>Latest Posts</h6>
                </div>
                <div class="card-body py-2" id="latest-posts-list">
                    @include('dashboard.partials.latest-posts', ['latestPosts' => $latestPosts])
                </div>
            </div>
        </div>
    </div>

</div>
<div class="card dashboard-card mt-3 fly-in">
    <div class="card-header bg-white border-0">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-arrow-repeat text-success me-1"></i>
            Offline Synchronization
        </h6>
    </div>

    <div class="card-body">

        <p class="mb-2">
            <strong>Status:</strong>
            <span id="sync-status" class="badge bg-success">
                Online
            </span>
        </p>

        <p class="mb-2">
            <strong>Pending Actions:</strong>
            <span id="pending-count">0</span>
        </p>

        <p class="mb-3">
            <strong>Last Sync:</strong>
            <span id="last-sync">
                Never
            </span>
        </p>

        <button
            class="btn btn-primary btn-sm"
            id="sync-now-btn">
            <i class="bi bi-arrow-repeat"></i>
            Sync Now
        </button>

    </div>
</div>

@endsection
