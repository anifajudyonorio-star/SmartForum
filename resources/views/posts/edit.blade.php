@extends('layouts.app')

@section('content')

<div class="container-fluid px-0" style="max-width:640px;">

    <div class="page-header mb-3">
        <a href="{{ route('topics.show', $post->topic) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to chat
        </a>
        <h1 class="page-title h5 mb-0">Edit Message</h1>
        <p class="page-subtitle">{{ $post->topic->Title }}</p>
    </div>

    <div class="profile-section">
        @if($errors->any())
            <div class="alert alert-danger small">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('posts.update', $post) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <textarea name="Post_Content" rows="5" class="form-control" required>{{ old('Post_Content', $post->Post_Content) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                <a href="{{ route('topics.show', $post->topic) }}" class="btn btn-secondary btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
