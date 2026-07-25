@if(auth()->user()->isAdmin())
    <a href="{{ route('groups.show', $group) }}" class="{{ $btnClass ?? 'btn btn-outline-primary btn-sm' }}">
        <i class="bi bi-eye me-1"></i> {{ $buttonLabel ?? 'Open group' }}
    </a>
@elseif($group->join_status === 'pending' || (isset($joinStatus) && $joinStatus === 'pending'))
    <span class="badge bg-warning text-dark">Pending approval</span>
@elseif($group->join_status === 'blocked' || (isset($joinStatus) && $joinStatus === 'blocked'))
    <span class="badge bg-secondary">Cannot join</span>
@elseif(filled($group->join_rules))
    <button type="button" class="{{ $btnClass ?? 'btn btn-outline-primary btn-sm' }}"
            data-bs-toggle="modal" data-bs-target="#joinRulesModal{{ $group->id }}">
        <i class="bi bi-person-plus me-1"></i> {{ $buttonLabel ?? 'Request to Join' }}
    </button>
    @include('groups.partials.join-rules-modal', ['group' => $group])
@else
    <form action="{{ route('groups.join', $group) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="{{ $btnClass ?? 'btn btn-outline-primary btn-sm' }}">
            <i class="bi bi-person-plus me-1"></i> {{ $buttonLabel ?? 'Request to Join' }}
        </button>
    </form>
@endif
