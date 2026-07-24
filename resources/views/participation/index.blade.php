@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success shadow-sm fly-in mb-3">{{ session('success') }}</div>
    @endif

    <div class="participation-header d-flex flex-wrap justify-content-between align-items-center gap-2 fly-in">
        <div>
            <h2 class="fw-bold mb-1" style="font-size:1.25rem;">
                <i data-feather="award" class="feather-icon me-1"></i>
                Participation Leaderboard
            </h2>
            <p class="text-muted small mb-0">
                @if($selectedGroup ?? null)
                    Engagement in <strong>{{ $selectedGroup->Group_Name }}</strong> using configurable criteria and manual marks.
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
        </div>
    </div>

    @if(($canManage ?? false) && ($selectedGroup ?? null) && ($settings ?? null))
        <div class="card mb-3 fly-in">
            <div class="card-header py-2"><strong>Grading criteria</strong></div>
            <div class="card-body">
                <form action="{{ route('participation.criteria.update', $selectedGroup) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-2">
                        <label class="form-label small">Points per topic</label>
                        <input type="number" min="0" max="100" class="form-control form-control-sm" name="topic_points" value="{{ $settings->topic_points }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Points per post</label>
                        <input type="number" min="0" max="100" class="form-control form-control-sm" name="post_points" value="{{ $settings->post_points }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Points per reply</label>
                        <input type="number" min="0" max="100" class="form-control form-control-sm" name="reply_points" value="{{ $settings->reply_points }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Gold min</label>
                        <input type="number" min="1" max="1000" class="form-control form-control-sm" name="gold_min" value="{{ $settings->gold_min }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Silver min</label>
                        <input type="number" min="1" max="1000" class="form-control form-control-sm" name="silver_min" value="{{ $settings->silver_min }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Bronze min</label>
                        <input type="number" min="1" max="1000" class="form-control form-control-sm" name="bronze_min" value="{{ $settings->bronze_min }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Max manual marks</label>
                        <input type="number" min="0" max="100" class="form-control form-control-sm" name="manual_marks_max" value="{{ $settings->manual_marks_max }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Save criteria</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                    </div>

                    <div class="participant-stats">
                        <div>
                            <p class="participant-stat-value">{{ $participant->topics_count }}</p>
                            <p class="participant-stat-label">Topics</p>
                        </div>
                        <div>
                            <p class="participant-stat-value">{{ $participant->posts_count }}</p>
                            <p class="participant-stat-label">Posts</p>
                        </div>
                        <div>
                            <p class="participant-stat-value">{{ $participant->replies_count }}</p>
                            <p class="participant-stat-label">Replies</p>
                        </div>
                    </div>

                    <div class="small text-muted mb-2">
                        Auto: <strong>{{ $participant->auto_score ?? 0 }}</strong>
                        · Manual: <strong>{{ $participant->manual_marks ?? 0 }}</strong>
                        · Total: <strong>{{ $participant->score }}</strong>
                    </div>

                    <div class="progress participant-progress mb-2">
                        <div class="progress-bar" style="width: {{ $progress }}%;"></div>
                    </div>

                    @if(($canManage ?? false) && ($selectedGroup ?? null))
                        <form action="{{ route('participation.grades.update', [$selectedGroup, $participant]) }}" method="POST" class="border-top pt-2 mt-2">
                            @csrf
                            @method('PATCH')
                            <div class="row g-2 align-items-end">
                                <div class="col-4">
                                    <label class="form-label small mb-0">Manual marks</label>
                                    <input type="number" min="0" max="{{ $settings->manual_marks_max ?? 20 }}" class="form-control form-control-sm"
                                           name="manual_marks" value="{{ $participant->manual_marks ?? 0 }}">
                                </div>
                                <div class="col-8">
                                    <label class="form-label small mb-0">Notes</label>
                                    <input type="text" class="form-control form-control-sm" name="notes"
                                           value="{{ $participant->grade_notes ?? '' }}" placeholder="Optional lecturer note">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save marks</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-4 fly-in">
            <p class="text-muted mb-0">No participation data available.</p>
        </div>
    @endif

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>feather.replace();</script>
@endpush
