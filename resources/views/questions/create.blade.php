@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Add New Question</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('questions.store') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label><strong>Select Quiz</strong></label>
            <select name="quiz_id" class="form-control" required>
                <option value="">-- Select Quiz --</option>

                @foreach($quizzes as $quiz)
                    <option value="{{ $quiz->id }}">
                        {{ $quiz->title }}
                    </option>
                @endforeach

            </select>
        </div>

        <div class="mb-3">
            <label><strong>Question</strong></label>

            <textarea
                name="question"
                class="form-control"
                rows="4"
                required>{{ old('question') }}</textarea>
        </div>

        <div class="mb-3">
            <label><strong>Question Type</strong></label>

            <select name="question_type" class="form-control">

                <option value="Multiple Choice">
                    Multiple Choice
                </option>

                <option value="True/False">
                    True / False
                </option>

                <option value="Short Answer">
                    Short Answer
                </option>

            </select>
        </div>

        <div class="mb-3">
            <label><strong>Marks</strong></label>

            <input
                type="number"
                name="marks"
                class="form-control"
                value="1"
                min="1"
                required>
        </div>

        <button type="submit" class="btn btn-success">
            Save Question
        </button>

        <a href="{{ route('questions.index') }}"
           class="btn btn-secondary">
            Back
        </a>

    </form>

</div>

@endsection