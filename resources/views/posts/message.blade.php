@php
    $mine = auth()->id() == $post->Created_By;
    $initials = strtoupper(substr($post->user->name ?? 'U', 0, 2));
@endphp

<div class="message-row {{ $mine ? 'mine' : 'theirs' }}">

    {{-- LEFT AVATAR (others only) --}}
    @unless($mine)
        <div class="avatar">
            {{ $initials }}
        </div>
    @endunless

    {{-- MESSAGE BUBBLE --}}
    <div class="bubble">

        {{-- HEADER --}}
        <div class="bubble-header">

            <strong class="username">
                {{ $post->user->name }}
            </strong>

            <div class="dropdown">

                <button class="btn btn-sm border-0 dropdown-toggle"
                        data-bs-toggle="dropdown">
                    ⋮
                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <button class="dropdown-item reply-btn"
                                data-post="{{ $post->id }}"
                                data-user="{{ $post->user->name }}">
                            Reply
                        </button>
                    </li>

                    @if($mine)
                        <li>
                            <a href="{{ route('posts.edit', $post) }}"
                               class="dropdown-item">
                                Edit
                            </a>
                        </li>

                        <li>
                            <form action="{{ route('posts.destroy', $post) }}"
                                  method="POST">
                                @csrf
                                @method('DELETE')

                                <button class="dropdown-item text-danger">
                                    Delete
                                </button>
                            </form>
                        </li>
                    @endif

                </ul>

            </div>
        </div>

        {{-- BODY --}}
        <div class="bubble-body">
            {{ $post->Post_Content }}
        </div>

        {{-- TIME --}}
        <div class="bubble-time">
            {{ $post->created_at->diffForHumans() }}
        </div>

    </div>

    {{-- RIGHT AVATAR (mine only) --}}
    @if($mine)
        <div class="avatar mine-avatar">
            {{ $initials }}
        </div>
    @endif

</div>

{{-- REPLIES --}}
@if($post->replies->count())

    <div class="reply-list">

        @foreach($post->replies as $reply)
            @include('posts.message', ['post' => $reply])
        @endforeach

    </div>

@endif