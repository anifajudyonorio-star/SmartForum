@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">
            <h3>Edit Group</h3>
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

            <form action="{{ route('groups.update', $group) }}" method="POST">

                @csrf
                @method('PUT')

                <div class="mb-3">

                    <label class="form-label">
                        Group Name
                    </label>

                    <input
                        type="text"
                        name="Group_Name"
                        class="form-control"
                        value="{{ old('Group_Name', $group->Group_Name) }}"
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
                        required>{{ old('Description', $group->Description) }}</textarea>

                </div>

                <button type="submit" class="btn btn-primary">
                    Update Group
                </button>

                <a href="{{ route('groups.show', $group) }}" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

@endsection