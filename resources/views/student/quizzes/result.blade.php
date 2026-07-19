@extends('layouts.app')

@section('content')

<div class="container">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Quiz Results</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th>Submission Status</th>
<td>{{ $result->submissionStatus() }}</td>
</tr>

<tr>

<th>Quiz</th>

<td>{{ $quiz->title }}</td>

</tr>

<tr>

<th>Quiz Score</th>

<td>{{ $score }} / {{ $totalMarks ?? '—' }}</td>

</tr>

<tr>

<th>Participation Marks</th>

<td>{{ $participationMarks }}</td>

</tr>

<tr class="table-success">

<th>Final Score</th>

<td><strong>{{ $totalScore }} / {{ $result->maximum_total_score ?? '—' }}</strong></td>

</tr>

<tr>
<th>Final Percentage</th>
<td>{{ $result->finalPercentage() !== null ? $result->finalPercentage().'%' : 'Snapshot unavailable' }}</td>
</tr>

</table>

<a href="{{ route('student.quizzes') }}" class="btn btn-primary">

Back to Quizzes

</a>

</div>

</div>

</div>

@endsection