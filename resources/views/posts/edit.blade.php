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
                <label class="form-label">Message</label>
                <textarea name="Post_Content" rows="5" class="form-control" required>{{ old('Post_Content', $post->Post_Content) }}</textarea>
            </div>

            @if($groupMembers->isNotEmpty())
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-eye-slash me-1"></i>Hide from members</label>
                    <p class="small text-muted">Selected members will not see this message.</p>
                    <div class="wa-exclude-list wa-exclude-list-edit">
                        @php $excluded = old('excluded_users', $post->hiddenFromUsers->pluck('id')->all()); @endphp
                        @foreach($groupMembers as $member)
                            <label class="wa-exclude-option">
                                <input type="checkbox" name="excluded_users[]" value="{{ $member->id }}"
                                       @checked(in_array($member->id, $excluded))>
                                <span>{{ $member->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                <a href="{{ route('topics.show', $post->topic) }}" class="btn btn-secondary btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
