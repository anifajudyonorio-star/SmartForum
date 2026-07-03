@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Add Answer Option</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('question-options.store') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label>Question</label>

            <select name="question_id" class="form-control" required>

                <option value="">Select Question</option>

                @foreach($questions as $question)
                    <option value="{{ $question->id }}">
                        {{ $question->question }}
                    </option>
                @endforeach

            </select>

        </div>

        <div class="mb-3">

            <label>Option Text</label>

            <input type="text"
                   name="option_text"
                   class="form-control"
                   required>

        </div>

        <div class="mb-3">

            <label>Is this the correct answer?</label>

            <select name="is_correct" class="form-control">

                <option value="0">No</option>

                <option value="1">Yes</option>

            </select>

        </div>

        <button class="btn btn-success">
            Save Option
        </button>

    </form>

</div>

@endsection