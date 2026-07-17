@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-patch-question-fill me-2 text-primary"></i>{{ $quiz->title }}</h1>
        <p class="page-subtitle">{{ $quiz->description }}</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="mb-3">
                <strong>Scheduled:</strong> {{ $quiz->start_time->format('M j, Y g:i A') }}<br>
                <strong>Ends:</strong> {{ $quiz->end_time->format('M j, Y g:i A') }}<br>
                <strong>Duration:</strong> {{ $quiz->duration }} min<br>
                <strong>Maximum score:</strong>
                {{ $quiz->authoredMarks() }} question marks
                + {{ (int) $quiz->participation_marks }} participation
                = {{ $quiz->authoredMaximumTotal() }}
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('student.quiz.show', $quiz) }}?start=1" class="btn btn-primary">Start Quiz</a>
                <a href="{{ route('student.quizzes') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>
@endsection
