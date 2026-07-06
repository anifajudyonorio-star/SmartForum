@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success shadow-sm fly-in mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    <div class="groups-page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3 fly-in">
        <div>
            <h1 class="groups-page-title">
                <i class="bi bi-people-fill me-2 text-primary"></i>Discussion Groups
            </h1>
            <p class="groups-page-subtitle">
                @if(auth()->user()->isStudent())
                    Explore lecturer groups, join discussions, and post under topics.
                @elseif(auth()->user()->isLecturer())
                    Create groups, post topics, and monitor student participation.
                @else
                    Manage all groups and forum activity across the platform.
                @endif
            </p>
        </div>

        @if(auth()->user()->canManageGroups())
            <a href="{{ route('groups.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Create Group
            </a>
        @endif
    </div>

    {{-- My Groups --}}
    <h6 class="fw-semibold mb-2 fly-in">My Groups</h6>

    @if($myGroups->count())
        <div class="row g-2 g-md-3 mb-4">
            @foreach($myGroups as $index => $group)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="group-card-modern fly-in {{ $index < 4 ? 'fly-in-delay-' . min($index + 1, 4) : '' }}">
                        <a href="{{ route('groups.show', $group) }}" class="text-decoration-none text-dark d-block">
                            <div class="group-card-icon-wrap">
                                <i class="bi bi-chat-square-text-fill"></i>
                            </div>
                            <h3 class="group-card-title">{{ $group->Group_Name }}</h3>
                            <p class="group-card-desc">{{ $group->Description ?: 'No description.' }}</p>
                            <div class="group-card-meta">
                                <span class="group-status-badge">{{ $group->Status }}</span>
                                <span class="group-topics-badge">
                                    <i class="bi bi-bookmark me-1"></i>{{ $group->topics_count }} Topics
                                </span>
                            </div>
                            <div class="group-card-footer">
                                <span>
                                    @if($group->user)
                                        <i class="bi bi-person me-1"></i>{{ $group->user->name }}
                                    @endif
                                </span>
                                <span class="group-card-action">Open <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </a>
                        @if(auth()->user()->isStudent())
                            <form action="{{ route('groups.leave', $group) }}" method="POST" class="mt-2" onsubmit="return confirm('Leave this group?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Leave Group</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card mb-4 p-3 text-center text-muted small fly-in">
            @if(auth()->user()->isStudent())
                You haven't joined any groups yet. Explore available groups below.
            @else
                You have no groups yet. Create one to get started.
            @endif
        </div>
    @endif

    {{-- Explore (students) --}}
    @if(auth()->user()->canJoinGroups() && $exploreGroups->count())
        <h6 class="fw-semibold mb-2 fly-in">Explore Groups</h6>
        <p class="text-muted small mb-3">Join a lecturer group to browse topics and participate in discussions.</p>

        <div class="row g-2 g-md-3">
            @foreach($exploreGroups as $index => $group)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="group-card-modern fly-in">
                        <div class="group-card-icon-wrap">
                            <i class="bi bi-compass"></i>
                        </div>
                        <h3 class="group-card-title">{{ $group->Group_Name }}</h3>
                        <p class="group-card-desc">{{ $group->Description ?: 'No description.' }}</p>
                        <div class="group-card-meta">
                            <span class="group-topics-badge">{{ $group->topics_count }} Topics</span>
                            @if($group->user)
                                <span class="group-topics-badge">
                                    <i class="bi bi-person me-1"></i>{{ $group->user->name }}
                                </span>
                            @endif
                        </div>
                        <form action="{{ route('groups.join', $group) }}" method="POST" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Join Group
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif(auth()->user()->canJoinGroups())
        <div class="card p-3 text-center text-muted small">
            No new lecturer groups available to join right now.
        </div>
    @endif

</div>

@endsection
