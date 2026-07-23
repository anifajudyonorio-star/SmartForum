@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <h1 class="page-title"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h1>
            <p class="page-subtitle mb-0">Unread items stay marked until you open them.</p>
        </div>
        <span id="notifPageUnreadBadge"
              class="badge bg-primary align-self-center {{ ($unreadCount ?? 0) > 0 ? '' : 'd-none' }}"
              data-notif-page-badge>{{ ($unreadCount ?? 0) }} unread</span>
    </div>

    <div id="notificationsList" class="notif-list">
        @forelse($notifications as $notification)
            @include('notification.partials.item', ['notification' => $notification])
        @empty
            @include('notification.partials.empty')
        @endforelse
    </div>

</div>

@endsection

@push('scripts')
<script>
    window.notificationsPageUrl = @json(route('notifications.index'));
</script>
@endpush
