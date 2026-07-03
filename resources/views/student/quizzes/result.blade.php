@extends('layouts.app')

@section('content')

<div class="container">

    <div class="alert alert-success">

        <h2>Quiz Submitted Successfully!</h2>

        <h3>Your Score: {{ $score }} Marks</h3>

    </div>

    <a href="{{ route('student.quizzes') }}" class="btn btn-primary">
        Back to Quiz List
    </a>

</div>

@endsection