@extends('layouts.app')

@section('body-class', 'chat-page')

@section('content')

@php
    $topicInitials = strtoupper(substr($topic->Title, 0, 2));
@endphp

<div class="wa-chat" id="waChat"
     data-topic-id="{{ $topic->id }}"
     data-store-url="{{ route('posts.store', $topic) }}">

    <header class="wa-chat-header">
        <a href="{{ route('groups.show', $topic->group) }}" class="wa-chat-back" aria-label="Back to group">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="wa-chat-avatar">{{ $topicInitials }}</div>
        <div class="wa-chat-info">
            <h1 class="wa-chat-title">{{ $topic->Title }}</h1>
            <p class="wa-chat-subtitle">{{ $topic->group->Group_Name }} &bull; {{ $posts->count() }} messages</p>
        </div>
        <div class="wa-chat-actions">
            <button type="button" class="btn" id="exportPdf" aria-label="Export PDF">
                <i class="bi bi-file-earmark-pdf"></i>
            </button>
        </div>
    </header>

    <div class="wa-messages" id="chatMessages">
        <div id="chatExportArea">
            @php $lastDate = null; @endphp
            @forelse($posts as $post)
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
            @empty
                <div class="wa-empty" id="chatEmpty">
                    <i class="bi bi-chat-dots"></i>
                    <p class="mb-0">No messages yet. Start the conversation below.</p>
                </div>
            @endforelse
        </div>
    </div>

    <footer class="wa-composer">
        <div id="replyPreview" class="wa-reply-bar d-none">
            <div class="wa-reply-label">
                <strong id="replyUser"></strong>
                <span id="replyText"></span>
            </div>
            <button type="button" class="btn-close btn-sm" id="cancelReply" aria-label="Cancel reply"></button>
        </div>

        <form id="chatForm" action="{{ route('posts.store', $topic) }}" method="POST">
            @csrf
            <input type="hidden" name="Parent_Post_ID" id="Parent_Post_ID">

            @if($groupMembers->isNotEmpty())
                <div id="excludePanel" class="wa-exclude-panel d-none">
                    <p class="wa-exclude-title"><i class="bi bi-eye-slash me-1"></i>Hide this message from:</p>
                    <div class="wa-exclude-list">
                        @foreach($groupMembers as $member)
                            <label class="wa-exclude-option">
                                <input type="checkbox" name="excluded_users[]" value="{{ $member->id }}" class="exclude-user-checkbox">
                                <span>{{ $member->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="wa-input-row">
                @if($groupMembers->isNotEmpty())
                    <button type="button" class="wa-exclude-toggle" id="excludeToggle" aria-label="Hide from members" title="Hide from members">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                @endif
                <textarea class="wa-textarea" name="Post_Content" id="messageInput"
                          rows="1" placeholder="Type a message" required></textarea>
                <button type="submit" class="wa-send-btn" id="sendBtn" aria-label="Send message">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </form>
    </footer>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
@endpush
