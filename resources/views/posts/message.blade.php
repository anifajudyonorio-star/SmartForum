@php
    $mine = auth()->id() == $post->Created_By;
    $parentVisible = $post->parent && $post->parent->isVisibleTo(auth()->user());
@endphp

<div class="wa-msg {{ $mine ? 'mine' : 'theirs' }}" id="msg-{{ $post->id }}" data-msg-id="{{ $post->id }}">
    <div class="wa-bubble-wrap">
        <div class="wa-bubble">
            <div class="wa-bubble-actions">
                <button type="button" class="wa-action-btn copy-btn" title="Copy">
                    <i class="bi bi-clipboard"></i>
                </button>
                <button type="button" class="wa-action-btn reply-btn"
                        data-post="{{ $post->id }}"
                        data-user="{{ $post->user->name }}"
                        data-content="{{ Str::limit($post->Post_Content, 80) }}"
                        title="Reply">
                    <i class="bi bi-reply-fill"></i>
                </button>
                @if(!$mine)
                    <button type="button" class="wa-action-btn report-btn"
                            data-post="{{ $post->id }}"
                            title="Report as irrelevant">
                        <i class="bi bi-flag-fill"></i>
                    </button>
                @endif
                @if($mine)
                    <a href="{{ route('posts.edit', $post) }}" class="wa-action-btn" title="Edit">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <form action="{{ route('posts.destroy', $post) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this message?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="wa-action-btn" title="Delete">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </form>
                @endif
            </div>

            @unless($mine)
                <div class="wa-bubble-name">{{ $post->user->name }}</div>
            @endunless

            @if($parentVisible)
                <div class="wa-quote reply-quote" data-scroll-to="msg-{{ $post->parent->id }}">
                    <div class="wa-quote-author">{{ $post->parent->user->name ?? 'User' }}</div>
                    <p class="wa-quote-text">{{ Str::limit($post->parent->Post_Content, 120) }}</p>
                </div>
            @endif

            <p class="wa-bubble-text">{{ $post->Post_Content }}</p>

            <div class="wa-bubble-meta">
                @if($mine && $post->hiddenFromUsers->isNotEmpty())
                    <span class="wa-hidden-badge" title="Hidden from {{ $post->hiddenFromUsers->pluck('name')->join(', ') }}">
                        <i class="bi bi-eye-slash"></i> {{ $post->hiddenFromUsers->count() }}
                    </span>
                @endif
                <span class="wa-bubble-time">{{ $post->created_at->format('g:i A') }}</span>
                @if($mine)
                    <span class="msg-tick msg-tick--sent" title="Sent">&#10003;&#10003;</span>
                @endif
            </div>
        </div>
    </div>
</div>
