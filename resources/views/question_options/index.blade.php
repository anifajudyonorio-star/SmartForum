@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Question Options</h2>

    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif

    <a href="{{ route('question-options.create') }}"
       class="btn btn-primary mb-3">

        Add New Option

    </a>

    <table class="table table-bordered">

        <thead>

        <tr>

            <th>ID</th>

            <th>Question</th>

            <th>Option</th>

            <th>Correct?</th>

            <th>Actions</th>

        </tr>

        </thead>

        <tbody>

        @forelse($options as $option)

            <tr>

                <td>{{ $option->id }}</td>

                <td>{{ $option->question->question }}</td>

                <td>{{ $option->option_text }}</td>

                <td>

                    @if($option->is_correct)

                        ✅ Yes

                    @else

                        ❌ No

                    @endif

                </td>

                <td>

                    <a href="{{ route('question-options.edit',$option->id) }}"
                       class="btn btn-warning btn-sm">

                        Edit

                    </a>

                    <form action="{{ route('question-options.destroy',$option->id) }}"
                          method="POST"
                          style="display:inline;">

                        @csrf

                        @method('DELETE')

                        <button class="btn btn-danger btn-sm">

                            Delete

                        </button>

                    </form>

                </td>

            </tr>

        @empty

            <tr>

                <td colspan="5">

                    No options found.

                </td>

            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@endsection