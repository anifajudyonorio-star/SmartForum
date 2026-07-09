@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">
            <h3>Create Group</h3>
            <p class="text-muted small mb-0">After creating a group, open it to add students and lecturers as members.</p>
        </div>

        <div class="card-body">

            @if ($errors->any())

                <div class="alert alert-danger">

                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>

            @endif

            <form action="{{ route('groups.store') }}" method="POST">

                @csrf

                <div class="mb-3">

                    <label class="form-label">
                        Group Name
                    </label>

                    <input
                        type="text"
                        name="Group_Name"
                        class="form-control"
                        value="{{ old('Group_Name') }}"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        name="Description"
                        rows="5"
                        class="form-control"
                        required>{{ old('Description') }}</textarea>

                </div>

                <button type="submit" class="btn btn-primary">
                    Create Group
                </button>

                <a href="{{ route('groups.index') }}" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

@endsection