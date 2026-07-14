@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h1>
        <p class="page-subtitle">Related activity is grouped into threads so you can follow conversations easily.</p>
    </div>

    <div class="notif-list">
        @forelse($notificationGroups as $group)
            @if(in_array($group['type'], ['reply_thread', 'topic_thread']))
                <section class="notif-thread fly-in {{ $group['has_unread'] ? 'has-unread' : '' }}">
                    <header class="notif-thread-header">
                        <div class="notif-thread-header-icon">
                            <i class="bi {{ $group['icon'] }}"></i>
                        </div>
                        <div class="notif-thread-header-body">
                            <div class="notif-thread-heading-row">
                                <h2 class="notif-thread-heading">{{ $group['heading'] }}</h2>
                                @if($group['count'] > 1)
                                    <span class="notif-thread-count">{{ $group['count'] }}</span>
                                @endif
                                @if($group['has_unread'])
                                    <span class="notif-thread-unread-dot" title="{{ $group['unread_count'] }} unread"></span>
                                @endif
                            </div>

                            @if($group['context'])
                                <p class="notif-thread-context">
                                    @if($group['context_url'])
                                        <a href="{{ $group['context_url'] }}">{{ $group['context'] }}</a>
                                    @else
                                        {{ $group['context'] }}
                                    @endif
                                </p>
                            @endif

                            @if($group['quote'])
                                <blockquote class="notif-thread-quote">{{ $group['quote'] }}</blockquote>
                            @endif
                        </div>
                    </header>

                    <div class="notif-thread-items">
                        @foreach($group['items'] as $index => $notification)
                            <a href="{{ route('notifications.read', $notification) }}"
                               class="notif-thread-item {{ !$notification->is_read ? 'unread' : '' }}"
                               style="--thread-index: {{ $index }}">
                                <div class="notif-thread-rail" aria-hidden="true">
                                    <span class="notif-thread-dot"></span>
                                    @if(!$loop->last)
                                        <span class="notif-thread-line"></span>
                                    @endif
                                </div>

                                <div class="notif-thread-card">
                                    <div class="notif-thread-card-top">
                                        <span class="notif-thread-author">{{ $notification->title }}</span>
                                        <span class="notif-thread-time">{{ $notification->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="notif-thread-message">{{ $notification->message }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @else
                @php $notification = $group['item']; @endphp
                @php
                    $icon = match($notification->Notification_Type) {
                        'Quiz' => 'bi-patch-question-fill',
                        'warning' => 'bi-exclamation-triangle-fill',
                        'PostCreated' => 'bi-chat-left-text-fill',
                        'reply' => 'bi-reply-fill',
                        default => 'bi-bell-fill',
                    };
                @endphp
                <a href="{{ route('notifications.read', $notification) }}"
                   class="notif-item {{ !$notification->is_read ? 'unread' : '' }} fly-in">
                    <div class="notif-icon">
                        <i class="bi {{ $icon }}"></i>
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
