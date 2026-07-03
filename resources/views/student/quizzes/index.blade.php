@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Available Quizzes</h2>

    @if($quizzes->count())

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>Quiz Title</th>
                <th>Duration</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        @foreach($quizzes as $quiz)

            <tr>

                <td>{{ $quiz->title }}</td>

                <td>{{ $quiz->duration }} Minutes</td>

                <td>
                    <a href="{{ route('student.quiz.show', $quiz->id) }}" class="btn btn-primary">
                        Start Quiz
                    </a>
                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

    @else

        <p>No quizzes available.</p>

    @endif

</div>

@endsection