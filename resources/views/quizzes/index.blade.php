@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Quiz List</h2>

    @if(session('success'))
        <div style="color:green; margin-bottom:15px;">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('quizzes.create') }}" class="btn btn-primary">
        Create New Quiz
    </a>

    <br><br>

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Category</th>
                <th>Duration</th>
                <th>Status</th>
                <th width="220">Actions</th>
            </tr>
        </thead>

        <tbody>

        @forelse($quizzes as $quiz)

            <tr>

                <td>{{ $quiz->id }}</td>
                <td>{{ $quiz->title }}</td>
                <td>{{ $quiz->category->category_name ?? 'No Category' }}</td>
                <td>{{ $quiz->duration }} Minutes</td>
                <td>{{ $quiz->status }}</td>

                <td>

                    <a href="{{ route('quizzes.edit', $quiz->id) }}"
                       class="btn btn-warning btn-sm">
                        Edit
                    </a>

                    <form action="{{ route('quizzes.destroy', $quiz->id) }}"
                          method="POST"
                          style="display:inline;">

                        @csrf
                        @method('DELETE')

                        <button class="btn btn-danger btn-sm"
                                onclick="return confirm('Delete this quiz?')">
                            Delete
                        </button>

                    </form>

                </td>

            </tr>

        @empty

            <tr>
                <td colspan="6">No quizzes found.</td>
            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@endsection