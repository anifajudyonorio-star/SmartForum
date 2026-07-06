@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm border-success">
        <div class="card-body text-center py-5">
            <h2 class="text-success mb-3">Quiz Submitted Successfully</h2>
            <p class="lead mb-1">Your score: <strong>{{ $score }}</strong> / {{ $totalMarks ?? $quiz->questions->sum('marks') }} marks</p>
            <p class="text-muted">{{ $quiz->title }}</p>
            <a href="{{ route('student.quizzes') }}" class="btn btn-primary mt-3">Back to Quizzes</a>
        </div>
    </div>
</div>
@endsection
