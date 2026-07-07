@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title">{{ $quiz->title }}</h1>
        <p class="page-subtitle">{{ $quiz->description }}</p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="badge bg-primary"><i class="bi bi-clock me-1"></i>{{ $quiz->duration }} min</span>
            <span class="badge" style="background:var(--primary-muted);color:var(--primary-dark);">
                <i class="bi bi-question-circle me-1"></i>{{ $quiz->questions->count() }} questions
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('student.quiz.submit', $quiz) }}">
        @csrf

        @foreach($quiz->questions as $question)
            <div class="card shadow-sm mb-3 fly-in">
                <div class="card-body">
                    <h5 class="card-title fs-6">{{ $loop->iteration }}. {{ $question->question }}
                        <span class="badge bg-secondary">{{ $question->marks }} marks</span>
                    </h5>

                    @forelse($question->options as $option)
                        <div class="form-check mb-2 py-1">
                            <input class="form-check-input" type="radio"
                                   name="question_{{ $question->id }}"
                                   id="q{{ $question->id }}_o{{ $option->id }}"
                                   value="{{ $option->id }}" required>
                            <label class="form-check-label" for="q{{ $question->id }}_o{{ $option->id }}">
                                {{ $option->option_text }}
                            </label>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No options configured.</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        <div class="d-flex gap-2 flex-wrap sticky-bottom-mobile pb-2">
            <button type="submit" class="btn btn-success flex-grow-1 flex-sm-grow-0">Submit Quiz</button>
            <a href="{{ route('student.quizzes') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
