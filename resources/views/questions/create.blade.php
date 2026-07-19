@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Add Question</h2>

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
            <form action="{{ route('questions.store') }}" method="POST" id="question-form">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Quiz</label>
                    <select name="quiz_id" class="form-select" required>
                        <option value="">-- Select Quiz --</option>
                        @foreach($quizzes as $quiz)
                            <option value="{{ $quiz->id }}" @selected(old('quiz_id') == $quiz->id)>{{ $quiz->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question</label>
                    <textarea name="question" class="form-control" rows="3" required>{{ old('question') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Question Type</label>
                    <select name="question_type" id="question_type" class="form-select">
                        <option value="Multiple Choice" @selected(old('question_type') === 'Multiple Choice')>Multiple Choice</option>
                        <option value="True/False" @selected(old('question_type') === 'True/False')>True / False</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Marks</label>
                    <input type="number" name="marks" class="form-control" value="{{ old('marks', 1) }}" min="1" required>
                </div>

                <div id="options-block">
                    <label class="form-label">Answer Options</label>
                    @for($i = 0; $i < 4; $i++)
                        <div class="input-group mb-2">
                            <span class="input-group-text">
                                <input type="radio" name="correct_option" value="{{ $i }}" @checked(old('correct_option', 0) == $i) required>
                            </span>
                            <input type="text" name="options[]" class="form-control" placeholder="Option {{ $i + 1 }}"
                                   value="{{ old('options.'.$i) }}">
                        </div>
                    @endfor
                    <small class="text-muted">Select the radio button next to the correct answer.</small>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Save Question</button>
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
