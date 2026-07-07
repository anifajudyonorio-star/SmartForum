@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h1>
        <p class="page-subtitle">Stay updated on replies, posts, and forum activity.</p>
    </div>

    <div class="notif-list">
        @forelse($notifications as $notification)
            <a href="{{ route('notifications.read', $notification) }}"
               class="notif-item {{ !$notification->is_read ? 'unread' : '' }} fly-in">
                <div class="notif-icon">
                    <i class="bi bi-chat-left-text-fill"></i>
                </div>
                <div class="notif-body">
                    <p class="notif-title">{{ $notification->title }}</p>
                    <p class="notif-message">{{ $notification->message }}</p>
                    <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                </div>
            </a>
        @empty
            <div class="groups-empty-state fly-in">
                <div class="groups-empty-icon"><i class="bi bi-bell-slash"></i></div>
                <p class="text-muted mb-0">No notifications yet.</p>
            </div>
        @endforelse
    </div>

</div>

@endsection
