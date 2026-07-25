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
                <p class="stat-label">Average Percentage</p>
                <p class="stat-number">{{ $averagePercentage !== null ? $averagePercentage.'%' : '—' }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-trophy-fill"></i></div>
                <p class="stat-label">Highest Percentage</p>
                <p class="stat-number">{{ $highestPercentage !== null ? $highestPercentage.'%' : '—' }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                <p class="stat-label">Pass Rate</p>
                <p class="stat-number">{{ $passRate !== null ? $passRate.'%' : '—' }}</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-4">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Comparable Attempts</p>
                <p class="stat-number">{{ $comparableAttempts }} / {{ $totalAttempts }}</p>
            </div>
        </div>
    </div>

    <div class="card fly-in fly-in-delay-4">
        <div class="card-header bg-white border-0 py-2">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-1 text-primary"></i>Student Results</h6>
        </div>
        <div class="card-body p-0">
            <div class="responsive-table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0 dashboard-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Student</th>
                                <th>Quiz</th>
                                <th>Quiz Score</th>
                                <th>Participation</th>
                                <th>Status</th>
                                <th class="pe-3">Final Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($results as $result)
                                <tr>
                                    <td class="ps-3 small">{{ $result->user->name ?? '—' }}</td>
                                    <td class="small">{{ $result->quiz->title ?? '—' }}</td>
                                    <td class="small">{{ $result->score }}</td>
                                    <td class="small">{{ $result->participation_marks }}</td>
                                    <td class="small">{{ $result->submissionStatus() }}</td>
                                    <td class="pe-3">
                                        <span class="badge bg-primary">
                                            {{ $result->total_score }} / {{ $result->maximum_total_score ?? 'snapshot unavailable' }}
                                            @if($result->finalPercentage() !== null)
                                                ({{ $result->finalPercentage() }}%)
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3 small">No quiz results found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="responsive-card-wrap p-2">
                <div class="data-card-list">
                    @forelse($results as $result)
                        <div class="data-card-item">
                            <p class="data-card-item-title">{{ $result->user->name ?? 'Unknown student' }}</p>
                            <p class="small text-muted mb-2">{{ $result->quiz->title ?? 'Deleted quiz' }}</p>
                            <div class="data-card-item-meta">
                                <span><i class="bi bi-check2-square me-1"></i>Score: {{ $result->score }}</span>
                                <span><i class="bi bi-star me-1"></i>Participation: {{ $result->participation_marks }}</span>
                                <span><i class="bi bi-info-circle me-1"></i>{{ $result->submissionStatus() }}</span>
                            </div>
                            <div class="data-card-item-actions">
                                <span class="badge bg-primary">
                                    {{ $result->total_score }} / {{ $result->maximum_total_score ?? 'snapshot unavailable' }}
                                    @if($result->finalPercentage() !== null)
                                        ({{ $result->finalPercentage() }}%)
                                    @endif
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small text-center py-3 mb-0">No quiz results found.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
