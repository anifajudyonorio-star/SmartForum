@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2">
        <div>
            <h1 class="page-title"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Quiz Progress</h1>
            <p class="page-subtitle mb-0">Your private quiz history and percentage progress. No other students' results are shown.</p>
        </div>
        <a href="{{ route('student.quizzes') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-patch-question me-1"></i>Available Quizzes
        </a>
    </div>

    <div class="alert alert-info small" role="note">
        Percentage statistics include only results with a valid saved final denominator.
        {{ $summary['total_attempted'] - $summary['comparable_attempts'] }} legacy {{ \Illuminate\Support\Str::plural('result', $summary['total_attempted'] - $summary['comparable_attempts']) }} {{ ($summary['total_attempted'] - $summary['comparable_attempts']) === 1 ? 'is' : 'are' }} excluded from percentage comparisons.
    </div>

    <div class="row g-2 mb-3">
        @php
            $stats = [
                ['label' => 'Total Attempted', 'value' => $summary['total_attempted'], 'icon' => 'bi-check2-square'],
                ['label' => 'Comparable', 'value' => $summary['comparable_attempts'], 'icon' => 'bi-bar-chart'],
                ['label' => 'Average', 'value' => $summary['average_percentage'] !== null ? $summary['average_percentage'].'%' : '—', 'icon' => 'bi-calculator'],
                ['label' => 'Best', 'value' => $summary['highest_percentage'] !== null ? $summary['highest_percentage'].'%' : '—', 'icon' => 'bi-trophy'],
                ['label' => 'Latest Comparable', 'value' => $summary['latest_percentage'] !== null ? $summary['latest_percentage'].'%' : '—', 'icon' => 'bi-clock-history'],
                ['label' => 'Pass Rate', 'value' => $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—', 'icon' => 'bi-award'],
            ];
        @endphp
        @foreach($stats as $stat)
            <div class="col-6 col-lg-2">
                <div class="stat-card stat-card-compact h-100 fly-in">
                    <div class="stat-card-icon"><i class="bi {{ $stat['icon'] }}"></i></div>
                    <p class="stat-label">{{ $stat['label'] }}</p>
                    <p class="stat-number">{{ $stat['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between gap-1">
            <strong><i class="bi bi-activity me-1 text-primary"></i>Chronological percentage progress</strong>
            <span class="small text-muted">
                @if($summary['trend'] === null)
                    Two comparable attempts are needed to calculate change.
                @elseif($summary['trend'] > 0)
                    Latest change: +{{ $summary['trend'] }} percentage points
                @elseif($summary['trend'] < 0)
                    Latest change: {{ $summary['trend'] }} percentage points
                @else
                    Latest change: no change
                @endif
            </span>
        </div>
        <div class="card-body">
            @if($chartData !== [])
                <div style="height: 300px;">
                    <canvas id="quizProgressChart" role="img" aria-label="Line chart of your comparable quiz percentages in chronological order"></canvas>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    The complete accessible values, including dates and scores, are listed in the history below.
                </p>
            @else
                <p class="text-muted mb-0">No comparable percentage data is available yet.</p>
            @endif
        </div>
    </div>

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Attempt history</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Question Score</th>
                                <th>Participation</th>
                                <th>Final Score</th>
                                <th>Percentage</th>
                                <th>Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['quiz']?->title ?? 'Deleted quiz' }}</strong>
                                        <div class="small text-muted">Result #{{ $row['result']->id }}</div>
                                    </td>
                                    <td>
                                        {{ $row['submitted_at']?->format('M j, Y g:i A') ?? 'Date unavailable' }}
                                    </td>
                                    <td>
                                        <span class="badge {{ str_contains($row['result']->submissionStatus(), 'Timed Out') ? 'bg-warning text-dark' : 'bg-success' }}">
                                            {{ $row['result']->submissionStatus() }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $row['result']->score }}
                                        /
                                        {{ $row['result']->maximum_score ?? 'snapshot unavailable' }}
                                    </td>
                                    <td>
                                        {{ $row['result']->participation_marks }}
                                        @if($row['maximum_participation'] !== null)
                                            / {{ $row['maximum_participation'] }}
                                        @endif
                                    </td>
                                    <td>
                                        <strong>
                                            {{ $row['result']->total_score }}
                                            /
                                            {{ $row['result']->maximum_total_score ?? 'snapshot unavailable' }}
                                        </strong>
                                    </td>
                                    <td>
                                        @if($row['percentage'] !== null)
                                            <span class="badge bg-primary">{{ $row['percentage'] }}%</span>
                                        @else
                                            <span class="text-muted">Not comparable</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['report_available'] && $row['quiz'])
                                            <a href="{{ route('quizzes.report', $row['quiz']) }}" class="btn btn-outline-primary btn-sm">
                                                Private report
                                            </a>
                                        @else
                                            <span class="small text-muted">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-clipboard2-x fs-2 text-muted d-block mb-2"></i>
                                        <strong>No quiz history yet.</strong>
                                        <div class="small text-muted">Completed and timed-out quizzes will appear here.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="responsive-card-wrap">
        <div class="data-card-list">
            @forelse($history as $row)
                <div class="data-card-item fly-in">
                    <p class="data-card-item-title">{{ $row['quiz']?->title ?? 'Deleted quiz' }}</p>
                    <div class="data-card-item-meta">
                        <span><i class="bi bi-calendar2-check me-1"></i>{{ $row['submitted_at']?->format('M j, Y g:i A') ?? 'Date unavailable' }}</span>
                        <span><i class="bi bi-info-circle me-1"></i>{{ $row['result']->submissionStatus() }}</span>
                        <span>Questions: {{ $row['result']->score }} / {{ $row['result']->maximum_score ?? 'snapshot unavailable' }}</span>
                        <span>Participation: {{ $row['result']->participation_marks }}@if($row['maximum_participation'] !== null) / {{ $row['maximum_participation'] }}@endif</span>
                        <span>Final: {{ $row['result']->total_score }} / {{ $row['result']->maximum_total_score ?? 'snapshot unavailable' }}</span>
                        <span>Percentage: {{ $row['percentage'] !== null ? $row['percentage'].'%' : 'Not comparable' }}</span>
                    </div>
                    @if($row['report_available'] && $row['quiz'])
                        <div class="data-card-item-actions">
                            <a href="{{ route('quizzes.report', $row['quiz']) }}" class="btn btn-outline-primary btn-sm w-100">Private report</a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="groups-empty-state">
                    <div class="groups-empty-icon"><i class="bi bi-clipboard2-x"></i></div>
                    <p class="text-muted mb-0">No quiz history yet.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@if($chartData !== [])
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const progressCanvas = document.getElementById('quizProgressChart');
            const progressLabels = {{ \Illuminate\Support\Js::from($chartLabels) }};
            const progressData = {{ \Illuminate\Support\Js::from($chartData) }};

            if (progressCanvas && window.Chart) {
                new Chart(progressCanvas, {
                    type: 'line',
                    data: {
                        labels: progressLabels,
                        datasets: [{
                            label: 'Final percentage',
                            data: progressData,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.12)',
                            pointBackgroundColor: '#15803d',
                            tension: 0.25,
                            fill: true,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: { display: true, text: 'Percentage' },
                            },
                        },
                    },
                });
            }
        </script>
    @endpush
@endif
