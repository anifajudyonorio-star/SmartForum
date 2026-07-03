@extends('layouts.app')

@section('content')

<div class="container">

<h2>Edit Option</h2>

<form action="{{ route('question-options.update',$option->id) }}" method="POST">

@csrf

@method('PUT')

<div class="mb-3">

<label>Question</label>

<select name="question_id" class="form-control">

@foreach($questions as $question)

<option value="{{ $question->id }}"
{{ $option->question_id==$question->id ? 'selected' : '' }}>

{{ $question->question }}

</option>

@endforeach

</select>

</div>

<div class="mb-3">

<label>Option</label>

<input type="text"
       name="option_text"
       class="form-control"
       value="{{ $option->option_text }}">

</div>

<div class="mb-3">

<label>Correct?</label>

<select name="is_correct" class="form-control">

<option value="0"
{{ !$option->is_correct ? 'selected' : '' }}>

No

</option>

<option value="1"
{{ $option->is_correct ? 'selected' : '' }}>

Yes

</option>

</select>

</div>

<button class="btn btn-success">

Update Option

</button>

</form>

</div>

@endsection