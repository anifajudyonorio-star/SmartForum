@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success mb-3 fly-in">{{ session('success') }}</div>
    @endif

    <div class="card mb-3 fly-in">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="min-width-0">
                    <h1 class="page-title h5 mb-1">{{ $group->Group_Name }}</h1>
                    <p class="text-muted small mb-2">{{ $group->Description }}</p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-primary">{{ $group->Status }}</span>
                        @if($group->user)
                            <span class="text-muted"><i class="bi bi-person me-1"></i>{{ $group->user->name }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    @if($canJoin ?? false)
                        <form action="{{ route('groups.join', $group) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Join
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

    <div class="d-flex justify-content-between align-items-center mb-2 fly-in">
        <h2 class="h6 fw-semibold mb-0">Discussion Topics</h2>

        @if($isMember && auth()->user()->canCreateTopics())
            <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New Topic
            </a>
        @endif
    </div>

    @if(! $isMember)
        <div class="alert alert-info small fly-in">
            Join this group to open topics and participate in discussions.
        </div>
    @endif

    @if($topics->count())
        <div class="topic-chat-list fly-in">
            @foreach($topics as $topic)
                @php $initials = strtoupper(substr($topic->Title, 0, 2)); @endphp
                @if($isMember)
                    <a href="{{ route('topics.show', $topic) }}" class="topic-chat-item">
                        <div class="topic-chat-avatar">{{ $initials }}</div>
                        <div class="topic-chat-content">
                            <p class="topic-chat-title">{{ $topic->Title }}</p>
                            <p class="topic-chat-desc">{{ Str::limit($topic->Topic_Description, 80) }}</p>
                        </div>
                        <i class="bi bi-chevron-right topic-chat-arrow"></i>
                    </a>
                @else
                    <div class="topic-chat-item opacity-75">
                        <div class="topic-chat-avatar">{{ $initials }}</div>
                        <div class="topic-chat-content">
                            <p class="topic-chat-title">{{ $topic->Title }}</p>
                            <p class="topic-chat-desc">{{ Str::limit($topic->Topic_Description, 80) }}</p>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="groups-empty-state fly-in">
            <div class="groups-empty-icon"><i class="bi bi-chat-square-text"></i></div>
            <p class="text-muted mb-2">
                @if(auth()->user()->canCreateTopics() && $isMember)
                    No topics yet. Create the first discussion.
                @else
                    No topics have been posted yet.
                @endif
            </p>
            @if(auth()->user()->canCreateTopics() && $isMember)
                <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">Create Topic</a>
            @endif
        </div>
    @endif

    <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary btn-sm mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Groups
    </a>

</div>

@endsection
