@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="mb-3 fly-in">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h2 class="fw-bold mb-1">Welcome back, Lecturer!</h2>
                <p class="text-muted small mb-0">Create groups & topics, then monitor student participation scores.</p>
            </div>
            <a href="{{ route('participation.index') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-bar-chart-fill me-1"></i> Participation
            </a>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">My Groups</p>
                <p class="stat-number">{{ $myGroups }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">My Topics</p>
                <p class="stat-number">{{ $myTopics }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-person-check-fill"></i></div>
                <p class="stat-label">Active Participants</p>
                <p class="stat-number">{{ $participants->count() }}</p>
            </div>
        </div>
    </div>

    <div class="card fly-in fly-in-delay-4">
        <div class="card-header bg-white py-2 border-0">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-fill me-1 text-primary"></i>Student Participation</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Name</th>
                            <th>Topics</th>
                            <th>Posts</th>
                            <th>Replies</th>
                            <th class="pe-3">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants as $participant)
                            <tr>
                                <td class="ps-3">{{ $participant->name }}</td>
                                <td>{{ $participant->topics_count }}</td>
                                <td>{{ $participant->posts_count }}</td>
                                <td>{{ $participant->replies_count }}</td>
                                <td class="pe-3"><span class="badge bg-primary">{{ $participant->score }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No participation data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
