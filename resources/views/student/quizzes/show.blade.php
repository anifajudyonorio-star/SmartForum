@extends('layouts.app')

@section('content')
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2>{{ $quiz->title }}</h2>

            <p class="text-muted">
                {{ $quiz->description }}
            </p>

            <small class="text-muted">
                Duration: {{ $quiz->duration }} minutes |
                {{ $quiz->questions->count() }} Questions
            </small>
        </div>

        <div class="text-end">
            <h5>⏰ Time Remaining</h5>

            <h2 id="timer" class="text-success fw-bold">
                {{ sprintf('%02d', $quiz->duration) }}:00
            </h2>
        </div>

    </div>

    <form id="quizForm"
          method="POST"
          action="{{ route('student.quiz.submit', $quiz) }}">

        @csrf

        @foreach($quiz->questions as $question)

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h5>

                    {{ $loop->iteration }}.

                    {{ $question->question }}

                    <span class="badge bg-secondary">

                        {{ $question->marks }} Marks

                    </span>

                </h5>

                @forelse($question->options as $option)

                    <div class="form-check mt-3">

                        <input

                            class="form-check-input"

                            type="radio"

                            name="question_{{ $question->id }}"

                            id="option{{ $option->id }}"

                            value="{{ $option->id }}">

                        <label class="form-check-label"

                               for="option{{ $option->id }}">

                            {{ $option->option_text }}

                        </label>

                    </div>

                @empty

                    <p class="text-danger">

                        No options available.

                    </p>

                @endforelse

            </div>

        </div>

        @endforeach

        <button

            type="submit"

            class="btn btn-success"

            onclick="return confirm('Are you sure you want to submit your quiz?')">

            Submit Quiz

        </button>

        <a href="{{ route('student.quizzes') }}"

           class="btn btn-secondary">

            Cancel

        </a>

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
        String(minutes).padStart(2,'0')
        + ":"
        + String(seconds).padStart(2,'0');

    if(timeLeft <= 300){

        timer.classList.remove('text-success');

        timer.classList.add('text-danger');

    }

    if(timeLeft <= 0){

        clearInterval(countdown);

        timer.innerHTML = "Submitting...";

        form.submit();

    }

    timeLeft--;

},1000);

</script>

@endsection