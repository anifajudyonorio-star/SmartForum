@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title">Performance Reports</h1>
        <p class="page-subtitle">Overview of all quiz results across the platform.</p>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-bar-chart-fill"></i></div>
                <p class="stat-label">Average Score</p>
                <p class="stat-number">{{ $averageScore }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-trophy-fill"></i></div>
                <p class="stat-label">Highest Score</p>
                <p class="stat-number">{{ $highestScore ?? '—' }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                <p class="stat-label">Lowest Score</p>
                <p class="stat-number">{{ $lowestScore ?? '—' }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Total Attempts</p>
                <p class="stat-number">{{ $totalAttempts }}</p>
            </div>
        </div>
    </div>

    <div class="card fly-in fly-in-delay-4">
        <div class="card-header bg-white border-0 py-2">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-1 text-primary"></i>Student Results</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 dashboard-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Student</th>
                            <th>Quiz</th>
                            <th>Quiz Score</th>
                            <th>Participation</th>
                            <th class="pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $result)
                            <tr>
                                <td class="ps-3 small">{{ $result->user->name ?? '—' }}</td>
                                <td class="small">{{ $result->quiz->title ?? '—' }}</td>
                                <td class="small">{{ $result->score }}</td>
                                <td class="small">{{ $result->participation_marks }}</td>
                                <td class="pe-3"><span class="badge bg-primary">{{ $result->total_score }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3 small">No quiz results found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
