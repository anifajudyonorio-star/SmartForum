@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Manage Quizzes</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('quiz-categories.index') }}" class="btn btn-outline-secondary">Categories</a>
            <a href="{{ route('questions.index') }}" class="btn btn-outline-secondary">Questions</a>
            <a href="{{ route('quizzes.create') }}" class="btn btn-primary">Create Quiz</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th width="260">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quizzes as $quiz)
                        <tr>
                            <td>{{ $quiz->title }}</td>
                            <td>{{ $quiz->category->category_name ?? '—' }}</td>
                            <td>{{ $quiz->questions_count }}</td>
                            <td>{{ $quiz->duration }} min</td>
                            <td><span class="badge bg-secondary">{{ $quiz->status }}</span></td>
                            <td>
                                <a href="{{ route('quizzes.edit', $quiz) }}" class="btn btn-warning btn-sm">Edit</a>
                                @if($quiz->status !== 'Active')
                                    <form action="{{ route('quizzes.publish', $quiz) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-success btn-sm">Publish</button>
                                    </form>
                                @endif
                                <form action="{{ route('quizzes.destroy', $quiz) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this quiz?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">No quizzes yet. Create a category, then add a quiz and questions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
