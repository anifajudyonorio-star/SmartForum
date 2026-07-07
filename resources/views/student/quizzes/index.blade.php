@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-patch-question-fill me-2 text-primary"></i>Available Quizzes</h1>
        <p class="page-subtitle">Take quizzes within their active time window. Each quiz can only be taken once.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Available Until</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quizzes as $quiz)
                                <tr>
                                    <td>
                                        <strong>{{ $quiz->title }}</strong>
                                        <div class="small text-muted">{{ $quiz->description }}</div>
                                    </td>
                                    <td>{{ $quiz->questions_count }}</td>
                                    <td>{{ $quiz->duration }} min</td>
                                    <td>{{ $quiz->end_time->format('M j, Y g:i A') }}</td>
                                    <td>
                                        @if($completedQuizIds->contains($quiz->id))
                                            <span class="badge bg-success">Completed</span>
                                        @else
                                            <span class="badge bg-primary">Available</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($completedQuizIds->contains($quiz->id))
                                            <span class="text-muted small">Already taken</span>
                                        @else
                                            <a href="{{ route('student.quiz.show', $quiz) }}" class="btn btn-primary btn-sm">Start Quiz</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">No quizzes are available right now.</td>
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
                    <p class="small text-muted mb-2">{{ $quiz->description }}</p>
                    <div class="data-card-item-meta">
                        <span><i class="bi bi-question-circle me-1"></i>{{ $quiz->questions_count }} questions</span>
                        <span><i class="bi bi-clock me-1"></i>{{ $quiz->duration }} min</span>
                        <span><i class="bi bi-calendar me-1"></i>{{ $quiz->end_time->format('M j, Y') }}</span>
                    </div>
                    <div class="data-card-item-actions">
                        @if($completedQuizIds->contains($quiz->id))
                            <span class="badge bg-success">Completed</span>
                        @else
                            <a href="{{ route('student.quiz.show', $quiz) }}" class="btn btn-primary btn-sm w-100">Start Quiz</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="groups-empty-state">
                    <div class="groups-empty-icon"><i class="bi bi-patch-question"></i></div>
                    <p class="text-muted mb-0">No quizzes available right now.</p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
