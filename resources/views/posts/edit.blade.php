@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">
            <h3>Edit Post</h3>
        </div>

        <div class="card-body">

            @if($errors->any())

                <div class="alert alert-danger">

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

                    <label class="form-label">
                        Post Title
                    </label>

                    <input
                        type="text"
                        name="post_title"
                        class="form-control"
                        value="{{ old('post_title', $post->post_title) }}"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Post Content
                    </label>

                    <textarea
                        name="Post_Content"
                        rows="6"
                        class="form-control"
                        required>{{ old('Post_Content', $post->Post_Content) }}</textarea>

                </div>

                <button type="submit" class="btn btn-primary">
                    Update Post
                </button>

                <a href="{{ route('topics.show', $post->topic) }}" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

@endsection