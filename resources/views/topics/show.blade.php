@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success mb-2 py-2 small">{{ session('success') }}</div>
    @endif

    <div class="chat-card shadow-sm fly-in">

        <div class="chat-card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <a href="{{ route('groups.show', $topic->group) }}" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>

                <div class="text-center flex-grow-1 px-2">
                    <h5 class="mb-0 fw-semibold">{{ $topic->Title }}</h5>
                    <small class="opacity-75">{{ $topic->group->Group_Name }}</small>
                </div>

                <button type="button" id="exportPdf" class="btn btn-sm btn-light">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </button>
            </div>
        </div>

        <div id="chatExportArea">
            <div id="chatBox" class="chat-area">
                @forelse($posts as $post)
                    @include('posts.message', ['post' => $post])
                @empty
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">No discussion yet. Start the conversation.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="chat-footer">
            <div id="replyPreview" class="alert alert-success py-2 px-3 small d-none mb-2">
                Replying to <strong id="replyUser"></strong>
                <button type="button" class="btn-close float-end" id="cancelReply" aria-label="Cancel reply"></button>
            </div>

            <form action="{{ route('posts.store', $topic) }}" method="POST">
                @csrf
                <input type="hidden" name="Parent_Post_ID" id="Parent_Post_ID">

                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="Post_Content"
                           placeholder="Type your message..." autocomplete="off" required>
                    <button class="btn btn-primary px-3">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.querySelectorAll('.reply-btn').forEach((button) => {
    button.addEventListener('click', function () {
        document.getElementById('replyPreview').classList.remove('d-none');
        document.getElementById('replyUser').innerText = this.dataset.user;
        document.getElementById('Parent_Post_ID').value = this.dataset.post;
        document.querySelector('input[name="Post_Content"]').focus();
    });
});

document.getElementById('cancelReply').addEventListener('click', function () {
    document.getElementById('replyPreview').classList.add('d-none');
    document.getElementById('Parent_Post_ID').value = '';
});

window.addEventListener('load', function () {
    const chat = document.getElementById('chatBox');
    if (chat) chat.scrollTop = chat.scrollHeight;

    const exportButton = document.getElementById('exportPdf');
    if (!exportButton || typeof html2pdf === 'undefined') return;

    exportButton.addEventListener('click', function () {
        const topicTitle = @json($topic->Title);
        const groupName = @json($topic->group->Group_Name);
        const wrapper = document.createElement('div');
        wrapper.style.padding = '16px';
        wrapper.innerHTML = `
            <h2 style="margin:0 0 4px;font-size:18px;">${topicTitle}</h2>
            <p style="margin:0 0 16px;color:#666;font-size:13px;">${groupName} — Exported ${new Date().toLocaleString()}</p>
        `;
        const clone = document.getElementById('chatExportArea').cloneNode(true);
        clone.querySelectorAll('.dropdown').forEach((el) => el.remove());
        wrapper.appendChild(clone);

        html2pdf().set({
            margin: 10,
            filename: `discussion-${topicTitle.replace(/[^a-z0-9]/gi, '-').toLowerCase()}.pdf`,
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(wrapper).save();
    });
});
</script>
@endpush
