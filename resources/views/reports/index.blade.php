@extends('layouts.app')

@section('content')

<div class="container">

<h2 class="mb-4">
Performance Report
</h2>

<div class="row mb-4">

<div class="col-md-3">
<div class="card text-center shadow">
<div class="card-body">
<h5>Average Score</h5>
<h3>{{ $averageScore }}</h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center shadow">
<div class="card-body">
<h5>Highest Score</h5>
<h3>{{ $highestScore }}</h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center shadow">
<div class="card-body">
<h5>Lowest Score</h5>
<h3>{{ $lowestScore }}</h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center shadow">
<div class="card-body">
<h5>Total Attempts</h5>
<h3>{{ $totalAttempts }}</h3>
</div>
</div>
</div>

</div>

<div class="card shadow">

<div class="card-header">

<h4 class="mb-0">
Student Results
</h4>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>Student</th>

<th>Quiz</th>

<th>Quiz Score</th>

<th>Participation</th>

<th>Total Score</th>

</tr>

</thead>

<tbody>

@forelse($results as $result)

<tr>

<td>{{ $result->student->name }}</td>

<td>{{ $result->quiz->title }}</td>

<td>{{ $result->score }}</td>

<td>{{ $result->participation_marks }}</td>

<td>
<strong>{{ $result->total_score }}</strong>
</td>

</tr>

@empty

<tr>

<td colspan="5" class="text-center">
No quiz results found.
</td>

</tr>

@endforelse

</tbody>

</table>

</div>

</div>

</div>

@endsection