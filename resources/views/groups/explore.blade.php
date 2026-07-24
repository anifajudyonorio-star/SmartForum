@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success shadow-sm fly-in mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger shadow-sm fly-in mb-3">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="groups-page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3 fly-in">
        <div>
            <h1 class="groups-page-title">
                <i class="bi bi-compass me-2 text-primary"></i>Explore Groups
            </h1>
            <p class="groups-page-subtitle">
                Discover discussion groups you are not in yet. Request to join and a group admin will review your request.
            </p>
        </div>

        <a href="{{ route('groups.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-people me-1"></i> My Groups
        </a>
    </div>

    @if($exploreGroups->count())
        <div class="row g-2 g-md-3 mb-4">
            @foreach($exploreGroups as $index => $group)
                @php
                    $joinStatus = $group->join_status ?? auth()->user()->joinRequestStatus($group);
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="group-card-modern fly-in {{ $index < 4 ? 'fly-in-delay-' . min($index + 1, 4) : '' }}">
                        <div class="p-3">
                            <div class="group-card-icon-wrap">
                                <i class="bi bi-chat-square-text-fill"></i>
                            </div>
                            <h3 class="group-card-title">{{ $group->Group_Name }}</h3>
                            <p class="group-card-desc">{{ $group->Description ?: 'No description.' }}</p>
                            <div class="group-card-meta mb-3">
                                <span class="group-status-badge">{{ $group->Status }}</span>
                                <span class="group-topics-badge">
                                    <i class="bi bi-bookmark me-1"></i>{{ $group->topics_count }} Topics
                                </span>
                                <span class="group-topics-badge">
                                    <i class="bi bi-people me-1"></i>{{ $group->members_count }} Members
                                </span>
                            </div>
                            <div class="group-card-footer d-flex justify-content-between align-items-center">
                                <span class="small text-muted">
                                    @if($group->user)
                                        <i class="bi bi-shield-check me-1"></i>{{ $group->user->name }}
                                    @endif
                                </span>

                                @include('groups.partials.join-request-button', [
                                    'group' => $group,
                                    'joinStatus' => $joinStatus,
                                    'buttonLabel' => 'Request to Join',
                                    'btnClass' => 'btn btn-primary btn-sm',
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="groups-empty-state fly-in">
            <div class="groups-empty-icon"><i class="bi bi-compass"></i></div>
            <p class="text-muted mb-2">
                There are no other groups to explore right now. You may already be in every available group.
            </p>
            <a href="{{ route('groups.index') }}" class="btn btn-outline-primary btn-sm">Back to My Groups</a>
        </div>
    @endif

</div>

@endsection
