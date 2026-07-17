@extends('layouts.app')

@section('content')

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Questions</h2>
        <a href="{{ route('questions.create') }}" class="btn btn-primary">
            Add New Question
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @forelse($quizzes as $quiz)
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">{{ $quiz->title }}</h5>
                    <small class="text-muted">{{ $quiz->description }}</small>
                </div>
                <span class="badge bg-secondary">{{ $quiz->questions->count() }} Questions</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Marks</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quiz->questions as $question)
                            <tr>
                                <td>{{ $question->id }}</td>
                                <td>{{ $question->question }}</td>
                                <td>{{ $question->question_type }}</td>
                                <td>{{ $question->marks }}</td>
                                <td>
                                    @if($quiz->canEditQuestions())
                                        <a href="{{ route('questions.edit', $question->id) }}" class="btn btn-warning btn-sm">
                                            Edit
                                        </a>
                                        <form action="{{ route('questions.destroy', $question->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this question?')">
                                                Delete
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">Published content is locked</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="alert alert-info">
            No quizzes with questions have been found.
        </div>
    @endforelse

</div>

@endsection