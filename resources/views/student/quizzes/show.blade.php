@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-sm-items-center gap-3">
            <div>
                <h1 class="page-title">{{ $quiz->title }}</h1>
                <p class="page-subtitle">{{ $quiz->description }}</p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge bg-primary"><i class="bi bi-clock me-1"></i>{{ $quiz->duration }} min</span>
                    <span class="badge" style="background:var(--primary-muted);color:var(--primary-dark);">
                        <i class="bi bi-question-circle me-1"></i>{{ $quiz->questions->count() }} questions
                    </span>
                </div>
            </div>

            <div class="text-sm-end">
                <p class="small text-muted mb-1">Time remaining</p>
                <h2 id="timer" class="text-success fw-bold mb-0">{{ sprintf('%02d', $quiz->duration) }}:00</h2>
            </div>
        </div>
    </div>

    <form id="quizForm" method="POST" action="{{ route('student.quiz.submit', $quiz) }}">
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

        <div class="d-flex gap-2 flex-wrap pb-2">
            <button type="submit" class="btn btn-success flex-grow-1 flex-sm-grow-0"
                    onclick="return confirm('Are you sure you want to submit your quiz?')">
                Submit Quiz
            </button>
            <a href="{{ route('student.quizzes') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

</div>

<script>
let timeLeft = {{ $quiz->duration * 60 }};

const timer = document.getElementById('timer');
const form = document.getElementById('quizForm');

const countdown = setInterval(function () {
    let minutes = Math.floor(timeLeft / 60);
    let seconds = timeLeft % 60;

    timer.innerHTML =
        String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

    if (timeLeft <= 300) {
        timer.classList.remove('text-success');
        timer.classList.add('text-danger');
    }

    if (timeLeft <= 0) {
        clearInterval(countdown);
        timer.innerHTML = 'Submitting...';
        form.submit();
    }

    timeLeft--;
}, 1000);
</script>
@endsection
