<a href="{{ $notification['url'] }}"
   class="notif-item {{ empty($notification['is_read']) ? 'unread' : '' }} fly-in"
   data-notif-item
   data-notif-id="{{ $notification['id'] }}"
   data-notif-url="{{ $notification['url'] }}">
    <div class="notif-icon">
        <i class="bi {{ $notification['icon'] }}"></i>
    </div>
    <div class="notif-body">
        <div class="notif-item-top">
            @if(empty($notification['is_read']))
                <span class="notif-unread-dot" aria-hidden="true">●</span>
            @endif
            <p class="notif-title">{{ $notification['title'] }}</p>
            <span class="notif-time">{{ $notification['time'] }}</span>
        </div>
        <p class="notif-message">{{ $notification['message'] }}</p>
    </div>
</a>
