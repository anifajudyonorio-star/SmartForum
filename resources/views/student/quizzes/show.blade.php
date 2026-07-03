@extends('layouts.app')

@section('content')

<div class="container">

    <h2>{{ $quiz->title }}</h2>

    <p><strong>Duration:</strong> {{ $quiz->duration }} Minutes</p>

    <hr>

    <form method="POST" action="{{ route('student.quiz.submit', $quiz->id) }}">
        @csrf

        @foreach($quiz->questions as $question)

            <div style="border:1px solid #ccc; padding:15px; margin-bottom:20px;">

                <h4>
                    {{ $loop->iteration }}. {{ $question->question }}
                </h4>

                @foreach($question->options as $option)

                    <div style="margin-left:20px; margin-bottom:10px;">

                        <label>

                            <input
                                type="radio"
                                name="question_{{ $question->id }}"
                                value="{{ $option->id }}"
                                required>

                            {{ $option->option_text }}

                        </label>

                    </div>

                @endforeach

            </div>

        @endforeach

        <button type="submit" class="btn btn-success">
            Submit Quiz
        </button>

    </form>

</div>

@endsection