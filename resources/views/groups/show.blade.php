@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h2 class="h5 fw-bold mb-1">{{ $group->Group_Name }}</h2>
                    <p class="text-muted small mb-2">{{ $group->Description }}</p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-primary">{{ $group->Status }}</span>
                        @if($group->user)
                            <span class="text-muted"><i class="bi bi-person me-1"></i>Lecturer: {{ $group->user->name }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex gap-2">
                    @if($canJoin ?? false)
                        <form action="{{ route('groups.join', $group) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Join Group
                            </button>
                        </form>
                    @endif

                    @if(auth()->user()->isLecturer() && (int) $group->Created_By === auth()->id())
                        <a href="{{ route('groups.edit', $group) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="h6 fw-semibold mb-0">Topics</h3>

        @if($isMember && auth()->user()->canCreateTopics())
            <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Create Topic
            </a>
        @endif
    </div>

    @if(! $isMember)
        <div class="alert alert-info small">
            Join this group to open topics and post in discussions.
        </div>
    @endif

    @if($topics->count())
        <div class="row g-2">
            @foreach($topics as $topic)
                <div class="col-12 col-md-6">
                    @if($isMember)
                        <a href="{{ route('topics.show', $topic) }}" class="text-decoration-none text-dark">
                    @endif
                        <div class="card h-100 {{ $isMember ? 'topic-card' : '' }}">
                            <div class="card-body py-3">
                                <h4 class="h6 fw-semibold mb-1">{{ $topic->Title }}</h4>
                                <p class="small text-muted mb-0">{{ Str::limit($topic->Topic_Description, 120) }}</p>
                                @if($isMember)
                                    <small class="text-primary mt-2 d-inline-block">Open discussion →</small>
                                @endif
                            </div>
                        </div>
                    @if($isMember)
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-info small">
            @if(auth()->user()->canCreateTopics() && $isMember)
                No topics yet. Create the first topic for this group.
            @else
                No topics have been posted in this group yet.
            @endif
        </div>
    @endif

    <a href="{{ route('groups.index') }}" class="btn btn-secondary btn-sm mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Groups
    </a>

</div>

<style>
.topic-card { transition: .2s; cursor: pointer; }
.topic-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(22,101,52,.1); }
</style>

@endsection
