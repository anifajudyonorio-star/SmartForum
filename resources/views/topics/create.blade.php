@extends('layouts.app')

@section('content')

<div class="container">

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card mb-4">

        <div class="card-header">
            <h2>Create Topic in {{ $group->Group_Name }}</h2>
        </div>

        <div class="card-body">

            <p>{{ $group->Description }}</p>

            <a href="{{ route('groups.show', $group) }}"
               class="btn btn-secondary">
                Back to Group
            </a>

        </div>

    </div>

    <div class="card mb-4">

        <div class="card-header">
            <h4>Create Topic</h4>
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

            <form action="{{ route('topics.store', $group) }}" method="POST">

                @csrf

                <div class="mb-3">

                    <label class="form-label">
                        Topic Title
                    </label>

                    <input
                        type="text"
                        name="Title"
                        class="form-control"
                        value="{{ old('Title') }}"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Topic Description
                    </label>

                    <textarea
                        name="Topic_Description"
                        rows="5"
                        class="form-control"
                        required>{{ old('Topic_Description') }}</textarea>

                </div>

                <button type="submit"
                        class="btn btn-primary">
                    Create Topic
                </button>

            </form>

        </div>

    </div>

</div>

@endsection