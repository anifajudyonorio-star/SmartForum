@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">{{ $quiz->title }}</h2>
        <p class="text-muted mb-0">{{ $quiz->description }}</p>
        <small class="text-muted">Duration: {{ $quiz->duration }} minutes · {{ $quiz->questions->count() }} questions</small>
    </div>

    <form method="POST" action="{{ route('student.quiz.submit', $quiz) }}">
        @csrf

        @foreach($quiz->questions as $question)
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="card-title">{{ $loop->iteration }}. {{ $question->question }} <span class="badge bg-secondary">{{ $question->marks }} marks</span></h5>

                    @forelse($question->options as $option)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio"
                                   name="question_{{ $question->id }}"
                                   id="q{{ $question->id }}_o{{ $option->id }}"
                                   value="{{ $option->id }}" required>
                            <label class="form-check-label" for="q{{ $question->id }}_o{{ $option->id }}">
                                {{ $option->option_text }}
                            </label>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No options configured for this question.</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-success">Submit Quiz</button>
        <a href="{{ route('student.quizzes') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
