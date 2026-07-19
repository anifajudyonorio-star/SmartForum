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
                    <span class="badge bg-secondary">
                        <i class="bi bi-calendar2-event me-1"></i>Scheduled: {{ $quiz->start_time->format('M j, Y g:i A') }}
                    </span>
                    <span class="badge bg-secondary">
                        <i class="bi bi-calendar-x me-1"></i>Ends: {{ $quiz->end_time->format('M j, Y g:i A') }}
                    </span>
                </div>
            </div>

            <div class="text-sm-end">
                <p class="small text-muted mb-1">Time remaining</p>
                <h2 id="timer" class="text-success fw-bold mb-0">
                    @php
                        $initial = $remainingSeconds ?? ($quiz->duration * 60);
                        $mins = intdiv($initial, 60);
                        $secs = $initial % 60;
                    @endphp
                    {{ sprintf('%02d', $mins) }}:{{ sprintf('%02d', $secs) }}
                </h2>
            </div>
        </div>
    </div>

    @if(now()->gte($quiz->end_time))
        @php
            $canView = auth()->user()->isAdmin() || auth()->user()->isLecturer() || 
                (is_null($quiz->group_id) || auth()->user()->groups->contains($quiz->group_id));
        @endphp

        @if($canView)
            <div class="mb-3">
                <a href="{{ route('quizzes.report', $quiz) }}" class="btn btn-outline-primary">View performance report</a>
            </div>
        @endif
    @endif

    <form id="quizForm" method="POST" action="{{ route('student.quiz.submit', $quiz) }}">
        @csrf
        <input type="hidden" name="attempt_id" value="{{ $attempt->id }}">

        <div class="alert alert-info py-2">
            Offline submissions are accepted only when synchronized before the server-stored attempt deadline.
            If synchronization happens later, queued answer changes are ignored and the attempt is auto-submitted.
        </div>

        @foreach($quiz->questions as $question)
            <div class="card shadow-sm mb-3 fly-in">
                <div class="card-body">
                    <h5 class="card-title fs-6">{{ $loop->iteration }}. {{ $question->question }}
                        <span class="badge bg-secondary">{{ $question->marks }} marks</span>
                    </h5>

                    @forelse($question->options as $option)
                        <div class="form-check mb-2 py-1">
                            <input class="form-check-input" type="radio"
                                   name="answers[{{ $question->id }}]"
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
let timeLeft = {{ $remainingSeconds ?? ($quiz->duration * 60) }};

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
        form.noValidate = true;
        form.requestSubmit();
    }

    timeLeft--;
}, 1000);
</script>
@endsection
