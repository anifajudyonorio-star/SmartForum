@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="dashboard-header fly-in">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="dashboard-title mb-0">Welcome back, Lecturer!</h2>
                <p class="text-muted small mb-0">Monitor student participation and track quiz performance across your groups.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.index') }}" class="btn btn-outline-primary btn-sm dashboard-action-btn">
                    <i class="bi bi-graph-up-arrow me-1"></i> Quiz Reports
                </a>
                <a href="{{ route('participation.index') }}" class="btn btn-primary btn-sm dashboard-action-btn">
                    <i class="bi bi-bar-chart-fill me-1"></i> Participation
                </a>
            </div>
        </div>
    </div>

    @include('dashboard.partials.group-admin-groups')

    <div class="row g-2 mb-2">
        <div class="col-6 col-md-4">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-1">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">My Groups</p>
                <p class="stat-number">{{ $myGroups }}</p>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-2">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">My Topics</p>
                <p class="stat-number">{{ $myTopics }}</p>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card stat-card-compact fly-in fly-in-delay-3">
                <div class="stat-card-icon"><i class="bi bi-person-check-fill"></i></div>
                <p class="stat-label">Active Participants</p>
                <p class="stat-number">{{ $participants->count() }}</p>
            </div>
        </div>
    </div>

    @php $summary = $quizProgress['summary']; @endphp
    <div class="card dashboard-card fly-in fly-in-delay-3 mb-3">
        <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="mb-0 fw-semibold"><i class="bi bi-patch-question-fill me-1 text-primary"></i>Student Quiz Progress</h6>
                <p class="small text-muted mb-0">Track previous quiz outcomes for students in your managed quizzes.</p>
            </div>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">View all reports</a>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-card-compact h-100">
                        <div class="stat-card-icon"><i class="bi bi-clipboard-check"></i></div>
                        <p class="stat-label">Submissions</p>
                        <p class="stat-number">{{ $summary['submissions'] }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-card-compact h-100">
                        <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                        <p class="stat-label">Students Assessed</p>
                        <p class="stat-number">{{ $summary['students_assessed'] }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-card-compact h-100">
                        <div class="stat-card-icon"><i class="bi bi-calculator"></i></div>
                        <p class="stat-label">Average Score</p>
                        <p class="stat-number">{{ $summary['average_percentage'] !== null ? $summary['average_percentage'].'%' : '—' }}</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-card-compact h-100">
                        <div class="stat-card-icon"><i class="bi bi-award"></i></div>
                        <p class="stat-label">Pass Rate</p>
                        <p class="stat-number">{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—' }}</p>
                    </div>
                </div>
            </div>

            @if($summary['comparable_attempts'] < $summary['submissions'])
                <div class="alert alert-info small mb-3" role="note">
                    Percentage charts use only results with a saved final denominator.
                    {{ $summary['submissions'] - $summary['comparable_attempts'] }} legacy
                    {{ \Illuminate\Support\Str::plural('result', $summary['submissions'] - $summary['comparable_attempts']) }}
                    {{ ($summary['submissions'] - $summary['comparable_attempts']) === 1 ? 'is' : 'are' }}
                    excluded from averages and charts.
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="border rounded p-3 h-100">
                        <h6 class="fw-semibold mb-2">Average Score by Quiz</h6>
                        @if(count($quizProgress['quizAverages']['values']) > 0)
                            <div style="height: 260px;">
                                <canvas id="lecturerQuizAverageChart" role="img" aria-label="Bar chart of average student percentages by quiz"></canvas>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No comparable quiz averages yet.</p>
                        @endif
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded p-3 h-100">
                        <h6 class="fw-semibold mb-2">Score Distribution</h6>
                        @if($summary['comparable_attempts'] > 0)
                            <div style="height: 260px;">
                                <canvas id="lecturerScoreDistributionChart" role="img" aria-label="Pie chart of student score distribution"></canvas>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No comparable score distribution yet.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h6 class="mb-0 fw-semibold">Recent Quiz Results</h6>
                <div class="d-flex align-items-center gap-2">
                    <label for="lecturerQuizFilter" class="small text-muted mb-0">Filter quiz</label>
                    <select id="lecturerQuizFilter" class="form-select form-select-sm" style="min-width: 180px;">
                        <option value="all">All quizzes</option>
                        @foreach($quizProgress['quizzes'] as $quiz)
                            <option value="{{ $quiz->id }}">{{ $quiz->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 dashboard-table" id="lecturerQuizResultsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Student</th>
                            <th>Quiz</th>
                            <th>Status</th>
                            <th>Final Score</th>
                            <th class="pe-3">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quizProgress['results'] as $result)
                            <tr data-quiz-id="{{ $result->quiz_id }}">
                                <td class="ps-3 small">{{ $result->user->name ?? '—' }}</td>
                                <td class="small">{{ $result->quiz->title ?? '—' }}</td>
                                <td class="small">{{ $result->submissionStatus() }}</td>
                                <td class="small">
                                    {{ $result->total_score }} / {{ $result->maximum_total_score ?? 'snapshot unavailable' }}
                                </td>
                                <td class="pe-3">
                                    @if($result->finalPercentage() !== null)
                                        <span class="badge bg-primary">{{ $result->finalPercentage() }}%</span>
                                    @else
                                        <span class="text-muted small">Not comparable</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr data-empty-row="1">
                                <td colspan="5" class="text-center text-muted py-3 small">
                                    No quiz results yet. Publish quizzes and wait for student submissions.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card dashboard-card fly-in fly-in-delay-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-fill me-1 text-primary"></i>Student Participation</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 dashboard-table">
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
                                <td class="ps-3 small">{{ $participant->name }}</td>
                                <td class="small">{{ $participant->topics_count }}</td>
                                <td class="small">{{ $participant->posts_count }}</td>
                                <td class="small">{{ $participant->replies_count }}</td>
                                <td class="pe-3"><span class="badge bg-primary">{{ $participant->score }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-2 small">No participation data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const averageLabels = @json($quizProgress['quizAverages']['labels']);
    const averageValues = @json($quizProgress['quizAverages']['values']);
    const distribution = @json($quizProgress['distribution']);

    const averageCanvas = document.getElementById('lecturerQuizAverageChart');
    if (averageCanvas && window.Chart && averageValues.length > 0) {
        new Chart(averageCanvas, {
            type: 'bar',
            data: {
                labels: averageLabels,
                datasets: [{
                    label: 'Average %',
                    data: averageValues,
                    backgroundColor: themeColorAlpha('primary', 0.65),
                    borderColor: themeColor('primary-dark'),
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Average (%)' },
                    },
                },
            },
        });
    }

    const distributionCanvas = document.getElementById('lecturerScoreDistributionChart');
    const distributionEntries = Object.entries(distribution).filter(([, count]) => count > 0);
    if (distributionCanvas && window.Chart && distributionEntries.length > 0) {
        new Chart(distributionCanvas, {
            type: 'pie',
            data: {
                labels: distributionEntries.map(([label]) => label),
                datasets: [{
                    data: distributionEntries.map(([, count]) => count),
                    backgroundColor: [
                        themeColor('primary'),
                        themeColor('primary-light'),
                        themeColor('warning'),
                        themeColor('danger-light'),
                    ],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            },
        });
    }

    const quizFilter = document.getElementById('lecturerQuizFilter');
    const resultsTable = document.getElementById('lecturerQuizResultsTable');
    quizFilter?.addEventListener('change', () => {
        const selected = quizFilter.value;
        let visible = 0;
        resultsTable?.querySelectorAll('tbody tr[data-quiz-id]').forEach((row) => {
            const match = selected === 'all' || row.dataset.quizId === selected;
            row.classList.toggle('d-none', !match);
            if (match) visible += 1;
        });
        const emptyRow = resultsTable?.querySelector('tbody tr[data-empty-row]');
        if (emptyRow) {
            emptyRow.classList.toggle('d-none', visible > 0 || selected === 'all');
        }
    });
</script>
@endpush
