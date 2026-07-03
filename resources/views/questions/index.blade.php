@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Questions</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('questions.create') }}" class="btn btn-primary mb-3">
        Add New Question
    </a>

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>ID</th>
                <th>Quiz</th>
                <th>Question</th>
                <th>Type</th>
                <th>Marks</th>
                <th width="180">Actions</th>
            </tr>
        </thead>

        <tbody>

        @forelse($questions as $question)

            <tr>

                <td>{{ $question->id }}</td>

                <td>{{ $question->quiz->title ?? 'No Quiz' }}</td>

                <td>{{ $question->question }}</td>

                <td>{{ $question->question_type }}</td>

                <td>{{ $question->marks }}</td>

                <td>

                    <a href="{{ route('questions.edit', $question->id) }}"
                       class="btn btn-warning btn-sm">
                        Edit
                    </a>

                    <form action="{{ route('questions.destroy', $question->id) }}"
                          method="POST"
                          style="display:inline;">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('Delete this question?')">
                            Delete
                        </button>

                    </form>

                </td>

            </tr>

        @empty

            <tr>
                <td colspan="6" class="text-center">
                    No questions found.
                </td>
            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@endsection