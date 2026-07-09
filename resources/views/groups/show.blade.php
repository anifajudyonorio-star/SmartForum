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
                            <span class="text-muted"><i class="bi bi-shield-check me-1"></i>Created by {{ $group->user->name }}</span>
                        @endif
                    </div>
                </div>

                @if($canManage ?? false)
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('groups.edit', $group) }}" class="btn btn-outline-secondary btn-sm">Edit Group</a>
                        <form action="{{ route('groups.destroy', $group) }}" method="POST"
                              onsubmit="return confirm('Delete this group and all its topics?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($canManage ?? false)
        <div class="card mb-3 fly-in">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people-fill me-1 text-primary"></i> Group Members</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('groups.members.add', $group) }}" method="POST" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Add student or lecturer</label>
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Select a user to add...</option>
                            @foreach($availableUsers as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->name }} ({{ ucfirst($user->role ?? 'student') }}) — {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary btn-sm" @disabled($availableUsers->isEmpty())>
                            <i class="bi bi-person-plus me-1"></i> Add Member
                        </button>
                    </div>
                </form>

                @if($members->isEmpty())
                    <p class="text-muted small mb-0">No members yet. Add students and lecturers above.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th width="100"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $member)
                                    <tr>
                                        <td>{{ $member->name }}</td>
                                        <td><span class="badge bg-secondary">{{ ucfirst($member->role ?? 'student') }}</span></td>
                                        <td class="small text-muted">{{ $member->email }}</td>
                                        <td>
                                            <form action="{{ route('groups.members.remove', [$group, $member]) }}" method="POST"
                                                  onsubmit="return confirm('Remove {{ $member->name }} from this group?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-2 fly-in">
        <h2 class="h6 fw-semibold mb-0">Discussion Topics</h2>

        @if($isMember && auth()->user()->canCreateTopics())
            <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New Topic
            </a>
        @endif
    </div>

    @if(! $isMember && ! ($canManage ?? false))
        <div class="alert alert-info small fly-in">
            You are not a member of this group. Contact an admin to be added.
        </div>
    @elseif(! $isMember && ($canManage ?? false))
        <div class="alert alert-info small fly-in">
            You are managing this group as admin. Add yourself as a member if you need to participate in discussions.
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
