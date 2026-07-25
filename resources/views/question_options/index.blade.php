@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3">
        <h2 class="page-title mb-0">Question Options</h2>
        <a href="{{ route('question-options.create') }}" class="btn btn-primary btn-sm">Add New Option</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Question</th>
                                <th>Option</th>
                                <th>Correct?</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($options as $option)
                                <tr>
                                    <td>{{ $option->id }}</td>
                                    <td>{{ $option->question->question }}</td>
                                    <td>{{ $option->option_text }}</td>
                                    <td>{{ $option->is_correct ? 'Yes' : 'No' }}</td>
                                    <td>
                                        <a href="{{ route('question-options.edit', $option->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <form action="{{ route('question-options.destroy', $option->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No options found.</td>
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
            @forelse($options as $option)
                <div class="data-card-item">
                    <p class="data-card-item-title">{{ $option->option_text }}</p>
                    <p class="small text-muted mb-2">{{ Str::limit($option->question->question, 120) }}</p>
                    <div class="data-card-item-meta">
                        <span><i class="bi bi-hash me-1"></i>#{{ $option->id }}</span>
                        <span><i class="bi bi-check2-circle me-1"></i>{{ $option->is_correct ? 'Correct' : 'Incorrect' }}</span>
                    </div>
                    <div class="data-card-item-actions">
                        <a href="{{ route('question-options.edit', $option->id) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="{{ route('question-options.destroy', $option->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted small text-center py-4 mb-0">No options found.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
