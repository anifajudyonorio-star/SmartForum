@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">

            <h2>{{ $post->post_title }}</h2>

        </div>

        <div class="card-body">

            <p>

                {{ $post->Post_Content }}

            </p>

            <hr>

            <small class="text-muted">

                Posted on {{ $post->created_at->format('d M Y H:i') }}

            </small>

            <br><br>

            <a href="{{ route('posts.edit',$post) }}"
               class="btn btn-warning">
                Edit
            </a>

            <form action="{{ route('posts.destroy',$post) }}"
                  method="POST"
                  class="d-inline">

                @csrf
                @method('DELETE')

                <button class="btn btn-danger">

                    Delete

                </button>

            </form>

            <a href="{{ route('topics.show',$post->topic) }}"
               class="btn btn-secondary">
                Back
            </a>

        </div>

    </div>

</div>

@endsection