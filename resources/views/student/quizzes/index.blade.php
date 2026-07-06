@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Available Quizzes</h2>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
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
                            <td colspan="6" class="text-center py-4">No quizzes are available right now. Check back later.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
