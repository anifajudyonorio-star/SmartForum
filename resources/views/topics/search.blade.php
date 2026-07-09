@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="dashboard-header fly-in">
        <h2 class="dashboard-title mb-0">
            <i class="bi bi-search me-1"></i> Search Topics
        </h2>
        <p class="text-muted small mb-0">Find discussions in your assigned groups by title or description.</p>
    </div>

    <div class="card dashboard-card mb-3 fly-in fly-in-delay-1">
        <div class="card-body">
            <form action="{{ route('topics.search') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label for="search" class="form-label auth-label mb-1">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control auth-input"
                        value="{{ $search ?? '' }}"
                        placeholder="Search by topic title or description..."
                        autocomplete="off">
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary auth-submit">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(($search ?? '') !== '')
        <p class="small text-muted mb-2">
            {{ $topics->count() }} result(s) for "<strong>{{ $search }}</strong>"
        </p>
    @endif

    @if($topics->count())
        <div class="row g-2">
            @foreach($topics as $topic)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card dashboard-card h-100 fly-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h6 class="fw-semibold mb-0">{{ $topic->Title }}</h6>
                                <span class="badge bg-primary">{{ $topic->posts_count }} posts</span>
                            </div>

                            @if($topic->group)
                                <span class="badge mb-2" style="background:var(--primary-muted);color:var(--primary-dark);">
                                    {{ $topic->group->Group_Name }}
                                </span>
                            @endif

                            <p class="small text-muted mb-2">
                                {{ Str::limit($topic->Topic_Description, 100) }}
                            </p>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    {{ optional($topic->user)->name ?? 'Unknown' }}
                                    &bull; {{ $topic->created_at->diffForHumans() }}
                                </small>
                                <a href="{{ route('topics.show', $topic) }}" class="btn btn-primary btn-sm">
                                    Open
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card dashboard-card text-center py-4 fly-in">
            <i class="bi bi-inbox text-muted fs-3 mb-2 d-block"></i>
            @if(($search ?? '') !== '')
                <p class="text-muted small mb-2">No topics matched your search.</p>
                <a href="{{ route('topics.search') }}" class="btn btn-outline-primary btn-sm">Clear search</a>
            @elseif(auth()->user()->groups()->count() === 0 && ! auth()->user()->isAdmin())
                <p class="text-muted small mb-2">Ask an admin to assign you to a group before searching its topics.</p>
                <a href="{{ route('groups.index') }}" class="btn btn-primary btn-sm">Explore Groups</a>
            @else
                <p class="text-muted small mb-0">No topics found in your groups yet.</p>
            @endif
        </div>
    @endif
</div>

@endsection
