@forelse($latestPosts as $post)
    <div class="mb-2">
        <strong class="small">
            {{ Str::limit($post->Post_Content, 50) }}
        </strong>
        <br>
        <small class="text-muted">
            {{ $post->created_at->diffForHumans() }}
        </small>
    </div>
@empty
    <p class="text-muted small mb-0">No recent posts.</p>
@endforelse
