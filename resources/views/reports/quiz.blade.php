@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title">Performance Report — {{ $quiz->title }}</h1>
        <p class="page-subtitle">Results for assigned members (after quiz end)</p>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Audience</p><p class="stat-number">{{ $metrics['audience_count'] }}</p></div></div>
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Submitted</p><p class="stat-number">{{ $metrics['submitted_count'] }}</p></div></div>
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Not Submitted</p><p class="stat-number">{{ $metrics['not_submitted_count'] }}</p></div></div>
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Timed Out</p><p class="stat-number">{{ $metrics['timed_out_count'] }}</p></div></div>
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Average</p><p class="stat-number">{{ $metrics['average_percentage'] !== null ? $metrics['average_percentage'].'%' : '—' }}</p></div></div>
        <div class="col-6 col-lg-2"><div class="stat-card stat-card-compact"><p class="stat-label">Pass Rate</p><p class="stat-number">{{ $metrics['pass_rate'] !== null ? $metrics['pass_rate'].'%' : '—' }}</p></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="mb-3">
                <strong>Percentage distribution:</strong>
                @foreach($metrics['distribution'] as $label => $count)
                    <span class="badge bg-secondary me-1">{{ $label }}: {{ $count }}</span>
                @endforeach
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['user']->name }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>
                                @if($row['result'])
                                    <div>
                                        Questions: {{ $row['result']->score }} / {{ $row['result']->maximum_score ?? 'snapshot unavailable' }}
                                    </div>
                                    <div>Participation: {{ $row['result']->participation_marks }}</div>
                                    <strong>
                                        Final: {{ $row['score'] }} / {{ $row['maximum_score'] ?? 'snapshot unavailable' }}
                                        @if($row['percentage'] !== null)
                                            ({{ $row['percentage'] }}%)
                                        @endif
                                    </strong>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
