@php $lastDate = null; @endphp
@foreach($posts as $post)
    @php
        $dateLabel = $post->created_at->isToday()
            ? 'Today'
            : ($post->created_at->isYesterday() ? 'Yesterday' : $post->created_at->format('M j, Y'));
    @endphp
    @if($lastDate !== $dateLabel)
        <div class="chat-date-divider"><span>{{ $dateLabel }}</span></div>
        @php $lastDate = $dateLabel; @endphp
    @endif
    @include('posts.message', ['post' => $post])
@endforeach
