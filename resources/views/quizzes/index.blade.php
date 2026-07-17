@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3 fly-in">
        <div>
            <h1 class="page-title"><i class="bi bi-patch-question-fill me-2 text-primary"></i>Manage Quizzes</h1>
            <p class="page-subtitle">Create quiz titles, quizzes, and questions. Publish when ready.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('quiz-categories.index') }}" class="btn btn-outline-secondary btn-sm">Title</a>
            <a href="{{ route('questions.index') }}" class="btn btn-outline-secondary btn-sm">Questions</a>
            <a href="{{ route('quizzes.create') }}" class="btn btn-primary btn-sm">Create Quiz</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Quiz Title</th>
                                <th>Quiz Title Template</th>
                                <th>Group</th>
                                <th>Questions</th>
                                <th>Maximum Marks</th>
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
                                    <td>{{ $quiz->group?->Group_Name ?? 'Unassigned' }}</td>
                                    <td>{{ $quiz->questions_count }}</td>
                                    <td>{{ $quiz->authoredMaximumTotal() }}</td>
                                    <td>{{ $quiz->duration }} min</td>
                                    <td><span class="badge bg-secondary">{{ $quiz->lifecycleStatus() }}</span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <a href="{{ route('quizzes.review', $quiz) }}" class="btn btn-outline-secondary btn-sm">Review</a>
                                            <a href="{{ route('quizzes.edit', $quiz) }}" class="btn btn-warning btn-sm">Edit</a>
                                            @if(!$quiz->isPublished())
                                                @if($quiz->questions_count > 0)
                                                    <form action="{{ route('quizzes.publish', $quiz) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-success btn-sm">Publish</button>
                                                    </form>
                                                @else
                                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                                        Add questions first
                                                    </button>
                                                @endif
                                            @endif
                                            @if($quiz->canBeDeleted())
                                                <form action="{{ route('quizzes.destroy', $quiz) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this draft quiz?')">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">No quizzes yet.</td>
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
            @forelse($quizzes as $quiz)
                <div class="data-card-item fly-in">
                    <p class="data-card-item-title">{{ $quiz->title }}</p>
                    <div class="data-card-item-meta">
                        <span>{{ $quiz->category->category_name ?? 'No quiz title' }}</span>
                        <span>{{ $quiz->group?->Group_Name ?? 'Unassigned' }}</span>
                        <span>{{ $quiz->questions_count }} questions</span>
                        <span>{{ $quiz->authoredMaximumTotal() }} maximum marks</span>
                        <span>{{ $quiz->duration }} min</span>
                        <span class="badge bg-secondary">{{ $quiz->lifecycleStatus() }}</span>
                    </div>
                    <div class="data-card-item-actions d-flex flex-wrap gap-2 align-items-center">
                        <a href="{{ route('quizzes.review', $quiz) }}" class="btn btn-outline-secondary btn-sm">Review</a>
                        <a href="{{ route('quizzes.edit', $quiz) }}" class="btn btn-warning btn-sm">Edit</a>
                        @if(!$quiz->isPublished())
                            @if($quiz->questions_count > 0)
                                <form action="{{ route('quizzes.publish', $quiz) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-success btn-sm">Publish</button>
                                </form>
                            @else
                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                    Add questions first
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="groups-empty-state">
                    <p class="text-muted mb-0">No quizzes yet. Create a quiz title first.</p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
