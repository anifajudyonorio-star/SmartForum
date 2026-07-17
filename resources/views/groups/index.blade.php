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
                Create groups, invite members, and assign admin or lecturer roles — just like WhatsApp.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('groups.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Create Group
            </a>
            <a href="{{ route('groups.explore') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-compass me-1"></i> Explore Groups
            </a>
        </div>
    </div>

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
                                @if(isset($group->members_count))
                                    <span class="group-topics-badge">
                                        <i class="bi bi-people me-1"></i>{{ $group->members_count }} Members
                                    </span>
                                @endif
                                @php
                                    $myRole = $group->pivot->Member_Role ?? null;
                                @endphp
                                @if($myRole)
                                    <span class="group-topics-badge">
                                        <i class="bi bi-person-badge me-1"></i>{{ ucfirst($myRole) }}
                                    </span>
                                @endif
                            </div>
                            <div class="group-card-footer">
                                <span>
                                    @if($group->user)
                                        <i class="bi bi-shield-check me-1"></i>{{ $group->user->name }}
                                    @endif
                                </span>
                                <span class="group-card-action">Open <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="groups-empty-state fly-in">
            <div class="groups-empty-icon"><i class="bi bi-people"></i></div>
            <p class="text-muted mb-2">
                No groups yet. Create one and invite others to join.
            </p>
            <a href="{{ route('groups.create') }}" class="btn btn-primary btn-sm">Create Group</a>
        </div>
    @endif

</div>

@endsection
