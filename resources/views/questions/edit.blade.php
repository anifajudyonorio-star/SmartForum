@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Edit Question</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('questions.update', $question) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Quiz</label>
                    <select name="quiz_id" class="form-select" required>
                        @foreach($quizzes as $quiz)
                            <option value="{{ $quiz->id }}" @selected(old('quiz_id', $question->quiz_id) == $quiz->id)>{{ $quiz->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question</label>
                    <textarea name="question" class="form-control" rows="3" required>{{ old('question', $question->question) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question Type</label>
                    <select name="question_type" id="question_type" class="form-select">
                        @foreach(['Multiple Choice', 'True/False'] as $type)
                            <option value="{{ $type }}" @selected(old('question_type', $question->question_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @if(!in_array($question->question_type, \App\Models\Question::GRADABLE_TYPES, true))
                        <div class="form-text text-danger">
                            This legacy {{ $question->question_type }} question must be converted before the quiz can be published.
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label">Marks</label>
                    <input type="number" name="marks" class="form-control" value="{{ old('marks', $question->marks) }}" min="1" required>
                </div>

                @php
                    $options = old('options', $question->options->pluck('option_text')->toArray());
                    $correctIndex = old('correct_option');
                    if ($correctIndex === null) {
                        $correctIndex = $question->options->search(fn ($opt) => $opt->is_correct);
                        if ($correctIndex === false) {
                            $correctIndex = 0;
                        }
                    }
                @endphp

                <div id="options-block">
                    <label class="form-label">Answer Options</label>
                    @for($i = 0; $i < 4; $i++)
                        <div class="input-group mb-2">
                            <span class="input-group-text">
                                <input type="radio" name="correct_option" value="{{ $i }}" @checked($correctIndex == $i) required>
                            </span>
                            <input type="text" name="options[]" class="form-control" placeholder="Option {{ $i + 1 }}"
                                   value="{{ $options[$i] ?? '' }}">
                        </div>
                    @endfor
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Update Question</button>
                    <a href="{{ route('questions.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('question_type').addEventListener('change', function () {
    syncQuestionOptions(this.value);
});

function syncQuestionOptions(type) {
    const trueFalse = type === 'True/False';
    const rows = document.querySelectorAll('#options-block .input-group');

    rows.forEach((row, index) => {
        const radio = row.querySelector('input[type="radio"]');
        const text = row.querySelector('input[type="text"]');
        const enabled = !trueFalse || index < 2;

        row.classList.toggle('d-none', !enabled);
        radio.disabled = !enabled;
        text.disabled = !enabled;
        text.required = enabled;
        text.readOnly = trueFalse && enabled;

        if (trueFalse && enabled) {
            text.value = index === 0 ? 'True' : 'False';
        }
    });
}

syncQuestionOptions(document.getElementById('question_type').value);
</script>
@endpush
