@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="participation-header d-flex flex-wrap justify-content-between align-items-center gap-2 fly-in">
        <div>
            <h2 class="fw-bold mb-1" style="font-size:1.25rem;">
                <i data-feather="award" class="feather-icon me-1"></i>
                Participation Leaderboard
            </h2>
            <p class="text-muted small mb-0">
                @if($selectedGroup ?? null)
                    Engagement in <strong>{{ $selectedGroup->Group_Name }}</strong>.
                @else
                    Track student engagement across topics, posts, and replies.
                @endif
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if(($availableGroups ?? collect())->count() > 1)
                <form method="GET" action="{{ route('participation.index') }}" class="d-flex gap-2 align-items-center">
                    <select name="group" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 180px;">
                        <option value="">All my groups</option>
                        @foreach($availableGroups as $g)
                            <option value="{{ $g->id }}" @selected(($selectedGroup->id ?? null) == $g->id)>
                                {{ $g->Group_Name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if($participants->count())
                <span class="badge bg-primary">
                    <i data-feather="star" class="feather-icon-sm me-1"></i>
                    Top: {{ $participants->first()->name }}
                </span>
            @endif
        </div>
    </div>

    @if($participants->count())
        <div class="participation-grid">
            @foreach($participants as $index => $participant)
                @php
                    $initials = strtoupper(substr($participant->name, 0, 2));
                    $progress = round(($participant->score / $highestScore) * 100);
                    $rankBadge = match(true) {
                        str_contains($participant->rank, 'Gold') => 'bg-warning text-dark',
                        str_contains($participant->rank, 'Silver') => 'bg-secondary',
                        str_contains($participant->rank, 'Bronze') => 'bg-danger',
                        default => 'bg-primary',
                    };
                @endphp

                <div class="participant-card fly-in {{ $index < 6 ? 'fly-in-delay-' . min($index + 1, 6) : '' }}">
                    <div class="participant-card-top">
                        <div class="participant-avatar">{{ $initials }}</div>
                        <div class="flex-grow-1 min-width-0">
                            <p class="participant-name text-truncate">{{ $participant->name }}</p>
                            <span class="badge {{ $rankBadge }}">{{ $participant->rank }}</span>
                        </div>
                        <i data-feather="trending-up" class="feather-icon text-primary"></i>
                    </div>

                    <div class="participant-stats">
                        <div>
                            <p class="participant-stat-value">{{ $participant->topics_count }}</p>
                            <p class="participant-stat-label"><i data-feather="book-open" class="feather-icon-sm"></i> Topics</p>
                        </div>
                        <div>
                            <p class="participant-stat-value">{{ $participant->posts_count }}</p>
                            <p class="participant-stat-label"><i data-feather="message-square" class="feather-icon-sm"></i> Posts</p>
                        </div>
                        <div>
                            <p class="participant-stat-value">{{ $participant->replies_count }}</p>
                            <p class="participant-stat-label"><i data-feather="corner-up-left" class="feather-icon-sm"></i> Replies</p>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">Score: <strong>{{ $participant->score }}</strong></small>
                        <small class="text-muted">{{ $progress }}%</small>
                    </div>
                    <div class="progress participant-progress">
                        <div class="progress-bar" style="width: {{ $progress }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-4 fly-in">
            <i data-feather="users" class="feather-icon-lg text-muted mb-2"></i>
            <p class="text-muted mb-0">No participation data available.</p>
        </div>
    @endif

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>feather.replace();</script>
@endpush
