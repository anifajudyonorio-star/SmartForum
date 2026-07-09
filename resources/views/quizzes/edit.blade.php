@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Edit Quiz</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('quizzes.update', $quiz) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Quiz Category</label>
                    <select name="category_id" class="form-select" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $quiz->category_id) == $category->id)>
                                {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $quiz->title) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" required>{{ old('description', $quiz->description) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration" class="form-control" value="{{ old('duration', $quiz->duration) }}" min="1" required>
                </div>
                <div class="mb-3">
                      <label class="form-label">Participation Marks</label>

                     <input
                         type="number"
                          name="participation_marks"
                        class="form-control"
                   value="{{ old('participation_marks',$quiz->participation_marks) }}"
        min="0"
        required>
</div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="datetime-local" name="start_time" class="form-control"
                               value="{{ old('start_time', $quiz->start_time?->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">End Time</label>
                        <input type="datetime-local" name="end_time" class="form-control"
                               value="{{ old('end_time', $quiz->end_time?->format('Y-m-d\TH:i')) }}" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        @foreach(['Draft', 'Scheduled', 'Active', 'Closed'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $quiz->status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-success">Update Quiz</button>
                <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
</div>
@endsection
