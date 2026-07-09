@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Create New Quiz</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('quizzes.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Select Quiz Title</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Select Quiz Title --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Assign To Group</label>
                    <select name="group_id" class="form-select" required>
                        <option value="">-- Select Group --</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>
                                {{ $group->Group_Name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" required>{{ old('description') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration" class="form-control" value="{{ old('duration', 15) }}" min="1" required>
                </div>
                <div class="mb-3">
    <label class="form-label">Participation Marks</label>
    <input
        type="number"
        name="participation_marks"
        class="form-control"
        value="{{ old('participation_marks',2) }}"
        min="0"
        required>
</div>

                <div class="mb-3">
                    <label class="form-label">Start Time</label>
                    <input type="datetime-local" name="start_time" class="form-control" value="{{ old('start_time') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">End Time</label>
                    <input type="datetime-local" name="end_time" class="form-control" value="{{ old('end_time') }}" required>
                </div>

                <button type="submit" class="btn btn-primary">Save Quiz</button>
                <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
