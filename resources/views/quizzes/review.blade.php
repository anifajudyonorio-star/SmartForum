@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Review Quiz</h2>
            <p class="text-muted mb-0">Review the questions added to this quiz before publishing.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Back to Quizzes</a>
            @if($quiz->questions_count > 0 && $quiz->status !== 'Active')
                <form action="{{ route('quizzes.publish', $quiz) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success">Publish Quiz</button>
                </form>
            @elseif($quiz->questions_count === 0)
                <button type="button" class="btn btn-outline-secondary" disabled>
                    Add questions before publishing
                </button>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h5">{{ $quiz->title }}</h3>
            <p class="text-muted mb-1"><strong>Quiz Title:</strong> {{ $quiz->category->category_name ?? 'No quiz title' }}</p>
            <p>{{ $quiz->description }}</p>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <strong>Duration</strong>
                    <p class="mb-0">{{ $quiz->duration }} minutes</p>
                </div>
                <div class="col-md-3">
                    <strong>Status</strong>
                    <p class="mb-0">{{ $quiz->status }}</p>
                </div>
                <div class="col-md-3">
                    <strong>Questions</strong>
                    <p class="mb-0">{{ $quiz->questions->count() }}</p>
                </div>
                <div class="col-md-3">
                    <strong>Assigned Group</strong>
                    <p class="mb-0">{{ $quiz->group?->Group_Name ?? 'Unassigned' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($quiz->questions->isEmpty())
        <div class="alert alert-info">
            This quiz has no questions yet. Add questions from the <a href="{{ route('questions.index') }}">Questions</a> page.
        </div>
    @else
        @foreach($quiz->questions as $question)
            <div class="card mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>#{{ $loop->iteration }}.</strong> {{ $question->question }}
                    </div>
                    <span class="badge bg-secondary">{{ $question->question_type }}</span>
                </div>
                <div class="card-body">
                    <p><strong>Marks:</strong> {{ $question->marks }}</p>

                    @if($question->options->isNotEmpty())
                        <div class="list-group">
                            @foreach($question->options as $option)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $option->option_text }}</span>
                                    @if($option->is_correct)
                                        <span class="badge bg-success">Correct</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3">
                        <a href="{{ route('questions.edit', $question) }}" class="btn btn-sm btn-warning">Edit question</a>
                        <form action="{{ route('questions.destroy', $question) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this question?')">
                                Delete question
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
