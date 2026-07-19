@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Quiz Titles</h2>
        <a href="{{ route('quiz-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Quiz Title
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Quiz Title</th>
                        <th>Description</th>
                        <th>Quizzes</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td>{{ $category->category_name }}</td>
                            <td>{{ $category->description ?: '—' }}</td>
                            <td>{{ $category->quizzes_count }}</td>
                            <td>
                                @can('update', $category)
                                    <a href="{{ route('quiz-categories.edit', $category) }}" class="btn btn-warning btn-sm">Edit</a>
                                @endcan
                                @can('delete', $category)
                                    <form action="{{ route('quiz-categories.destroy', $category) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this quiz title?')">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">No quiz titles yet. Create one to start building quizzes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
