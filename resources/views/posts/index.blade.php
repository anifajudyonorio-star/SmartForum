@extends('layouts.app')

@section('content')

<div class="container">

    <h2 class="mb-4">Posts</h2>

    @forelse($posts as $post)

        <div class="card mb-3">

            <div class="card-body">

                <h4>{{ $post->post_title }}</h4>

                <p>{{ Str::limit($post->Post_Content,150) }}</p>

                <a href="{{ route('posts.show',$post) }}"
                   class="btn btn-success btn-sm">
                    View
                </a>

                <a href="{{ route('posts.edit',$post) }}"
                   class="btn btn-warning btn-sm">
                    Edit
                </a>

            </div>

        </div>

    @empty

        <div class="alert alert-info">

            No posts available.

        </div>

    @endforelse

</div>

@endsection