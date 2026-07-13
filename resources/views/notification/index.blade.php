@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h1>
        <p class="page-subtitle">Stay updated on replies, posts, and forum activity.</p>
    </div>

    <div class="notif-list">
        @forelse($notificationGroups as $group)
            @if($group['type'] === 'reply_stack')
                <div class="notif-reply-stack fly-in" style="--stack-count: {{ count($group['items']) - 1 }}">
                    @foreach($group['items'] as $index => $notification)
                        <a href="{{ route('notifications.read', $notification) }}"
                           class="notif-item notif-reply-stack-item {{ !$notification->is_read ? 'unread' : '' }}"
                           style="--stack-index: {{ $index }}">
                            <div class="notif-icon">
                                <i class="bi bi-reply-fill"></i>
                            </div>
                            <div class="notif-body">
                                <p class="notif-title">{{ $notification->title }}</p>
                                <p class="notif-message">{{ $notification->message }}</p>
                                <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                @php $notification = $group['item']; @endphp
                <a href="{{ route('notifications.read', $notification) }}"
                   class="notif-item {{ !$notification->is_read ? 'unread' : '' }} fly-in">
                    <div class="notif-icon">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <div class="notif-body">
                        <p class="notif-title">{{ $notification->title }}</p>
                        <p class="notif-message">{{ $notification->message }}</p>
                        @if($notification->Notification_Type === 'Quiz' && $notification->quiz)
                            <div class="small text-muted mt-1">
                                <div><i class="bi bi-calendar2-event me-1"></i>Scheduled: {{ $notification->quiz->start_time?->format('M j, Y g:i A') }}</div>
                                <div><i class="bi bi-calendar-x me-1"></i>Ends: {{ $notification->quiz->end_time?->format('M j, Y g:i A') }}</div>
                            </div>
                        @endif
                        <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                </a>
            @endif
        @empty
            <div class="groups-empty-state fly-in">
                <div class="groups-empty-icon"><i class="bi bi-bell-slash"></i></div>
                <p class="text-muted mb-0">No notifications yet.</p>
            </div>
        @endforelse
    </div>

</div>

@endsection
