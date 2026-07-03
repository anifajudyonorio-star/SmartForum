@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Create New Quiz</h2>

    @if ($errors->any())
        <div style="color:red; margin-bottom:15px;">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('quizzes.store') }}" method="POST">
        @csrf

        <div style="margin-bottom:15px;">
            <label><strong>Quiz Category</strong></label><br>
            <select name="category_id" required>
                <option value="">-- Select Category --</option>

                @foreach($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->category_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom:15px;">
            <label><strong>Quiz Title</strong></label><br>
            <input type="text" name="title" value="{{ old('title') }}" required>
        </div>

        <div style="margin-bottom:15px;">
            <label><strong>Description</strong></label><br>
            <textarea name="description">{{ old('description') }}</textarea>
        </div>

        <div style="margin-bottom:15px;">
            <label><strong>Duration (Minutes)</strong></label><br>
            <input type="number" name="duration" value="{{ old('duration') }}" min="1" required>
        </div>

        <div style="margin-bottom:15px;">
            <label><strong>Start Time</strong></label><br>
            <input type="datetime-local" name="start_time" required>
        </div>

        <div style="margin-bottom:15px;">
            <label><strong>End Time</strong></label><br>
            <input type="datetime-local" name="end_time" required>
        </div>

        <button type="submit">
            Save Quiz
        </button>

    </form>

</div>

@endsection