@extends('layouts.app')

@section('content')

@php
    $canParticipate = $isMember && auth()->user()->canParticipateInGroup($group);
@endphp

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success mb-3 fly-in">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-3 fly-in">{{ session('error') }}</div>
    @endif

    <div class="card mb-3 fly-in">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="min-width-0">
                    <h1 class="page-title h5 mb-1">{{ $group->Group_Name }}</h1>
                    <p class="text-muted small mb-2">{{ $group->Description }}</p>
                    <div class="d-flex flex-wrap gap-2 small align-items-center">
                        <span class="badge bg-primary">{{ $group->Status }}</span>
                        @if($group->user)
                            <span class="text-muted"><i class="bi bi-shield-check me-1"></i>Created by {{ $group->user->name }}</span>
                        @endif
                        @if($groupRole)
                            <span class="badge
                                @if($groupRole === 'admin') bg-danger
                                @elseif($groupRole === 'lecturer') bg-info text-dark
                                @else bg-secondary
                                @endif">
                                Your role: {{ ucfirst($groupRole) }}
                            </span>
                        @endif
                    </div>
                </div>

                @if($canManage ?? false)
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('statistics.group', $group) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-graph-up-arrow me-1"></i> Statistics
                        </a>
                        <a href="{{ route('participation.group', $group) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-bar-chart-fill me-1"></i> Participation
                        </a>
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

    <div class="row g-2 g-md-3 mb-3 fly-in">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                <p class="stat-label">Total Members</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['members_count'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-bookmark-fill"></i></div>
                <p class="stat-label">Total Topics</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['topics_count'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <p class="stat-label">Total Posts</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['posts_count'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-person-check-fill"></i></div>
                <p class="stat-label">Active Members</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['active_members'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-pause-circle-fill"></i></div>
                <p class="stat-label">Suspended</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['suspended_members'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-slash-circle-fill"></i></div>
                <p class="stat-label">Blocked</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['blocked_members'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 mb-3 fly-in">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact stat-card-highlight h-100">
                <div class="stat-card-icon"><i class="bi bi-trophy-fill"></i></div>
                <p class="stat-label">Most Active Member</p>
                <div class="stat-value">
                    @if($groupStats['most_active_member'])
                        <p class="stat-name">{{ $groupStats['most_active_member']['name'] }}</p>
                        <p class="stat-meta">{{ $groupStats['most_active_member']['count'] }} {{ $groupStats['most_active_member']['label'] }}</p>
                    @else
                        <p class="stat-name text-muted">—</p>
                        <p class="stat-meta">No posts yet</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact stat-card-highlight h-100">
                <div class="stat-card-icon"><i class="bi bi-journal-text"></i></div>
                <p class="stat-label">Top Topic Creator</p>
                <div class="stat-value">
                    @if($groupStats['top_topic_creator'])
                        <p class="stat-name">{{ $groupStats['top_topic_creator']['name'] }}</p>
                        <p class="stat-meta">{{ $groupStats['top_topic_creator']['count'] }} {{ $groupStats['top_topic_creator']['label'] }}</p>
                    @else
                        <p class="stat-name text-muted">—</p>
                        <p class="stat-meta">No topics yet</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact stat-card-highlight h-100">
                <div class="stat-card-icon"><i class="bi bi-fire"></i></div>
                <p class="stat-label">Most Active Topic</p>
                <div class="stat-value">
                    @if($groupStats['most_active_topic'])
                        <p class="stat-name" title="{{ $groupStats['most_active_topic']['name'] }}">{{ Str::limit($groupStats['most_active_topic']['name'], 22) }}</p>
                        <p class="stat-meta">{{ $groupStats['most_active_topic']['count'] }} {{ $groupStats['most_active_topic']['label'] }}</p>
                    @else
                        <p class="stat-name text-muted">—</p>
                        <p class="stat-meta">No posts yet</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-calculator-fill"></i></div>
                <p class="stat-label">Avg Posts / Topic</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['avg_posts_per_topic'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <p class="stat-label">Members with Warnings</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['members_with_warnings'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card stat-card-compact h-100">
                <div class="stat-card-icon"><i class="bi bi-shield-fill-check"></i></div>
                <p class="stat-label">Group Admins</p>
                <div class="stat-value">
                    <p class="stat-number">{{ $groupStats['admin_count'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2 fly-in">
        <h2 class="h6 fw-semibold mb-0">Discussion Topics</h2>

        @if($canParticipate)
            <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New Topic
            </a>
        @endif
    </div>

    @if(! $isMember && ! ($canManage ?? false))
        <div class="alert alert-info small fly-in">
            You are not a member of this group. Use <a href="{{ route('groups.explore') }}">Explore Groups</a> to request access.
        </div>
    @elseif(! $isMember && ($canManage ?? false) && auth()->user()->isAdmin())
        <div class="alert alert-info small fly-in">
            You are viewing this group as a system admin. Join requests are not required for oversight.
        </div>
    @elseif($isMember && ! $canParticipate)
        <div class="alert alert-warning small fly-in">
            Your access in this group is restricted. You cannot create topics or post until a group admin reinstates you.
        </div>
    @endif

    @if($topics->count())
        <div class="topic-chat-list fly-in mb-3">
            @foreach($topics as $topic)
                @php $initials = strtoupper(substr($topic->Title, 0, 2)); @endphp
                @if(auth()->user()->canViewGroup($group))
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
        <div class="groups-empty-state fly-in mb-3">
            <div class="groups-empty-icon"><i class="bi bi-chat-square-text"></i></div>
            <p class="text-muted mb-2">
                @if($canParticipate)
                    No topics yet. Create the first discussion.
                @else
                    No topics have been posted yet.
                @endif
            </p>
            @if($canParticipate)
                <a href="{{ route('topics.create', $group) }}" class="btn btn-primary btn-sm">Create Topic</a>
            @endif
        </div>
    @endif

    @include('groups.partials.post-reports')

    <div class="card mb-3 fly-in">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-people-fill me-1 text-primary"></i>
                Group Members
                <span class="text-muted fw-normal">({{ $members->count() }})</span>
            </h6>
        </div>
        <div class="card-body">
            @if(($canManage ?? false) && ($pendingJoinRequests ?? collect())->isNotEmpty())
                <div class="mb-4" id="join-requests">
                    <h6 class="small fw-semibold mb-2">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i> Pending Join Requests
                    </h6>
                    <div class="responsive-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th style="min-width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingJoinRequests as $requester)
                                        <tr>
                                            <td>{{ $requester->name }}</td>
                                            <td>{{ $requester->email }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <form action="{{ route('groups.join.approve', [$group, $requester]) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form action="{{ route('groups.join.reject', [$group, $requester]) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Decline</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="responsive-card-wrap mb-3">
                        <div class="data-card-list">
                            @foreach($pendingJoinRequests as $requester)
                                <div class="data-card-item">
                                    <p class="data-card-item-title">{{ $requester->name }}</p>
                                    <p class="small text-muted mb-2">{{ $requester->email }}</p>
                                    <div class="data-card-item-actions d-flex gap-1">
                                        <form action="{{ route('groups.join.approve', [$group, $requester]) }}" method="POST" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm w-100">Approve</button>
                                        </form>
                                        <form action="{{ route('groups.join.reject', [$group, $requester]) }}" method="POST" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if($canManage ?? false)
                <form action="{{ route('groups.members.add', $group) }}" method="POST" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Add member</label>
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Select a user to add...</option>
                            @foreach($availableUsers as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->name }} — {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Role</label>
                        <select name="Member_Role" class="form-select form-select-sm">
                            <option value="member" selected>Member</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-sm" @disabled($availableUsers->isEmpty())>
                            <i class="bi bi-person-plus me-1"></i> Add Member
                        </button>
                    </div>
                </form>
            @endif

            @if($members->isEmpty())
                <p class="text-muted small mb-0">No members yet.</p>
            @else
                <div class="responsive-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Group Role</th>
                                <th>Status</th>
                                @if($canManage ?? false)
                                    <th>Warnings</th>
                                @endif
                                <th>Email</th>
                                @if($canManage ?? false)
                                    <th style="min-width: 220px;">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($members as $member)
                                @php
                                    $memberGroupRole = $member->pivot->Member_Role ?? 'member';
                                    $memberStatus = $member->pivot->Member_Status ?? 'Active';
                                    $memberWarnings = (int) ($member->pivot->warnings ?? 0);
                                    $isSelf = (int) $member->id === (int) auth()->id();
                                    $isOtherAdmin = $memberGroupRole === 'admin' && ! auth()->user()->isAdmin();
                                @endphp
                                <tr class="@if($memberStatus === 'Blocked') table-danger @elseif($memberStatus === 'Suspended') table-warning @endif">
                                    <td>
                                        {{ $member->name }}
                                        @if((int) $member->id === (int) $group->Created_By)
                                            <span class="badge bg-light text-muted border ms-1">Creator</span>
                                        @endif
                                        @if($isSelf)
                                            <span class="text-muted small ms-1">(You)</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($canManage ?? false)
                                            <form action="{{ route('groups.members.role', [$group, $member]) }}" method="POST" class="d-flex gap-1 align-items-center">
                                                @csrf
                                                @method('PATCH')
                                                <select name="Member_Role" class="form-select form-select-sm" style="width: auto;"
                                                        onchange="this.form.submit()">
                                                    <option value="member" @selected($memberGroupRole === 'member')>Member</option>
                                                    <option value="lecturer" @selected($memberGroupRole === 'lecturer')>Lecturer</option>
                                                    <option value="admin" @selected($memberGroupRole === 'admin')>Admin</option>
                                                </select>
                                            </form>
                                        @else
                                            <span class="badge
                                                @if($memberGroupRole === 'admin') bg-danger
                                                @elseif($memberGroupRole === 'lecturer') bg-info text-dark
                                                @else bg-secondary
                                                @endif">
                                                {{ ucfirst($memberGroupRole) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if($memberStatus === 'Active') bg-success
                                            @elseif($memberStatus === 'Suspended') bg-warning text-dark
                                            @else bg-danger
                                            @endif">
                                            {{ $memberStatus }}
                                        </span>
                                    </td>
                                    @if($canManage ?? false)
                                        <td>
                                            <span class="badge {{ $memberWarnings >= 2 ? 'bg-danger' : ($memberWarnings === 1 ? 'bg-warning text-dark' : 'bg-light text-dark') }}">
                                                {{ $memberWarnings }}/2
                                            </span>
                                        </td>
                                    @endif
                                    <td class="small text-muted">{{ $member->email }}</td>
                                    @if($canManage ?? false)
                                        <td>
                                            @if($isSelf)
                                                <span class="text-muted small">You</span>
                                            @elseif($isOtherAdmin)
                                                <span class="text-muted small">Protected</span>
                                            @else
                                                <div class="d-flex flex-wrap gap-1">
                                                    @if($memberStatus === 'Active')
                                                        <button type="button" class="btn btn-warning btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#warnModal{{ $member->id }}">
                                                            Warn
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#suspendModal{{ $member->id }}">
                                                            Suspend
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#blockModal{{ $member->id }}">
                                                            Block
                                                        </button>
                                                    @else
                                                        <form action="{{ route('groups.members.reinstate', [$group, $member]) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success btn-sm">Reinstate</button>
                                                        </form>
                                                    @endif
                                                    <form action="{{ route('groups.members.remove', [$group, $member]) }}" method="POST"
                                                          onsubmit="return confirm('Remove {{ $member->name }} from this group?')"
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                                                    </form>
                                                </div>

                                                {{-- Warn modal --}}
                                                <div class="modal fade" id="warnModal{{ $member->id }}" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form action="{{ route('groups.members.warn', [$group, $member]) }}" method="POST" class="modal-content">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title">Warn {{ $member->name }}</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="small text-muted">Warning {{ $memberWarnings + 1 }}/2 in this group. At 2 warnings the member is auto-suspended.</p>
                                                                <label class="form-label small">Reason (optional)</label>
                                                                <textarea name="reason" class="form-control form-control-sm" rows="3"></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning btn-sm">Issue Warning</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                {{-- Suspend modal --}}
                                                <div class="modal fade" id="suspendModal{{ $member->id }}" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form action="{{ route('groups.members.suspend', [$group, $member]) }}" method="POST" class="modal-content">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title">Suspend {{ $member->name }}</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="small text-muted">Suspended members can view the group but cannot post or create topics.</p>
                                                                <label class="form-label small">Reason (optional)</label>
                                                                <textarea name="reason" class="form-control form-control-sm" rows="3"></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                {{-- Block modal --}}
                                                <div class="modal fade" id="blockModal{{ $member->id }}" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form action="{{ route('groups.members.block', [$group, $member]) }}" method="POST" class="modal-content">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h6 class="modal-title">Block {{ $member->name }}</h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="small text-muted">Blocked members lose access to this group until reinstated.</p>
                                                                <label class="form-label small">Reason (optional)</label>
                                                                <textarea name="reason" class="form-control form-control-sm" rows="3"></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger btn-sm">Block</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="responsive-card-wrap">
                    <div class="data-card-list">
                        @foreach($members as $member)
                            @php
                                $memberGroupRole = $member->pivot->Member_Role ?? 'member';
                                $memberStatus = $member->pivot->Member_Status ?? 'Active';
                                $memberWarnings = (int) ($member->pivot->warnings ?? 0);
                                $isSelf = (int) $member->id === (int) auth()->id();
                                $isOtherAdmin = $memberGroupRole === 'admin' && ! auth()->user()->isAdmin();
                            @endphp
                            <div class="data-card-item">
                                <p class="data-card-item-title">
                                    {{ $member->name }}
                                    @if((int) $member->id === (int) $group->Created_By)
                                        <span class="badge bg-light text-muted border ms-1">Creator</span>
                                    @endif
                                </p>
                                <div class="data-card-item-meta">
                                    <span><i class="bi bi-person-badge me-1"></i>{{ ucfirst($memberGroupRole) }}</span>
                                    <span><i class="bi bi-activity me-1"></i>{{ $memberStatus }}</span>
                                    @if($canManage ?? false)
                                        <span><i class="bi bi-exclamation-triangle me-1"></i>{{ $memberWarnings }}/2 warns</span>
                                    @endif
                                    <span><i class="bi bi-envelope me-1"></i>{{ $member->email }}</span>
                                </div>
                                @if($canManage ?? false)
                                    <div class="data-card-item-actions d-flex flex-wrap gap-1">
                                        @if($isSelf)
                                            <span class="text-muted small">You</span>
                                        @elseif($isOtherAdmin)
                                            <span class="text-muted small">Protected</span>
                                        @else
                                            @if($memberStatus === 'Active')
                                                <button type="button" class="btn btn-warning btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#warnModal{{ $member->id }}">Warn</button>
                                                <button type="button" class="btn btn-outline-warning btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#suspendModal{{ $member->id }}">Suspend</button>
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#blockModal{{ $member->id }}">Block</button>
                                            @else
                                                <form action="{{ route('groups.members.reinstate', [$group, $member]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm">Reinstate</button>
                                                </form>
                                            @endif
                                            <form action="{{ route('groups.members.remove', [$group, $member]) }}" method="POST"
                                                  onsubmit="return confirm('Remove {{ $member->name }} from this group?')" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary btn-sm mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Groups
    </a>

</div>

@endsection
