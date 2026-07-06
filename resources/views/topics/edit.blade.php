@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">
    <div class="card">
        <div class="card-header py-2">
            <h3 class="h6 mb-0">Edit Topic</h3>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger small">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('topics.update', $topic) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label small">Topic Title</label>
                    <input type="text" name="Title" class="form-control form-control-sm"
                           value="{{ old('Title', $topic->Title) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Topic Description</label>
                    <textarea name="Topic_Description" rows="4" class="form-control form-control-sm" required>{{ old('Topic_Description', $topic->Topic_Description) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Update Topic</button>
                <a href="{{ route('topics.show', $topic) }}" class="btn btn-secondary btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@endsection
