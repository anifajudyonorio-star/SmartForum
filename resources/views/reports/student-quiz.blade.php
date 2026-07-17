@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title">Your Result — {{ $quiz->title }}</h1>
        <p class="page-subtitle">Your detailed result and privacy-safe group performance after the quiz closed.</p>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Personal result</strong></div>
        <div class="card-body">
            @if($personalResult)
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">{{ $personalResult->submissionStatus() }}</dd>
                    <dt class="col-sm-4">Question score</dt>
                    <dd class="col-sm-8">{{ $personalResult->score }} / {{ $personalResult->maximum_score ?? 'snapshot unavailable' }}</dd>
                    <dt class="col-sm-4">Participation</dt>
                    <dd class="col-sm-8">{{ $personalResult->participation_marks }}</dd>
                    <dt class="col-sm-4">Final score</dt>
                    <dd class="col-sm-8">
                        <strong>{{ $personalResult->total_score }} / {{ $personalResult->maximum_total_score ?? 'snapshot unavailable' }}</strong>
                        @if($personalResult->finalPercentage() !== null)
                            ({{ $personalResult->finalPercentage() }}%)
                        @endif
                    </dd>
                </dl>
            @else
                <p class="text-muted mb-0">You did not submit this quiz.</p>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Anonymized group summary</strong></div>
        <div class="card-body">
            @if($summary)
                <p>
                    Based on {{ $summary['submission_count'] }} comparable submissions:
                    average {{ $summary['average_percentage'] }}%,
                    pass rate {{ $summary['pass_rate'] }}%.
                </p>
                <div>
                    @foreach($summary['distribution'] as $label => $count)
                        <span class="badge bg-secondary me-1">{{ $label }}: {{ $count }}</span>
                    @endforeach
                </div>
            @else
                <p class="text-muted mb-0">
                    Aggregate performance is hidden until at least {{ $privacyThreshold }} comparable submissions exist.
                    Other students' names, scores, and rankings are never shown here.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
