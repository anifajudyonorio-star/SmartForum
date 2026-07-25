@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3">
        <h2 class="page-title mb-0">Quiz Titles</h2>
        <a href="{{ route('quiz-categories.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Quiz Title
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
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
    </div>

    <div class="responsive-card-wrap">
        <div class="data-card-list">
            @forelse($categories as $category)
                <div class="data-card-item fly-in">
                    <p class="data-card-item-title">{{ $category->category_name }}</p>
                    <p class="small text-muted mb-2">{{ $category->description ?: 'No description.' }}</p>
                    <div class="data-card-item-meta">
                        <span><i class="bi bi-patch-question me-1"></i>{{ $category->quizzes_count }} quizzes</span>
                    </div>
                    <div class="data-card-item-actions">
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
                    </div>
                </div>
            @empty
                <div class="groups-empty-state">
                    <p class="text-muted mb-0">No quiz titles yet. Create one to start building quizzes.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
