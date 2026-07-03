@extends('layouts.app')

@section('content')

<div class="card shadow">

<div class="card-header bg-primary text-white d-flex justify-content-between">

<h4>Quiz Categories</h4>

<a href="{{ route('quiz-categories.create') }}"
class="btn btn-light">

<i class="bi bi-plus-circle"></i>

Add Category

</a>

</div>

<div class="card-body">

@if(session('success'))

<div class="alert alert-success">

{{ session('success') }}

</div>

@endif

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Category</th>
<th>Description</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

@forelse($categories as $category)

<tr>

<td>{{ $category->id }}</td>

<td>{{ $category->category_name }}</td>

<td>{{ $category->description }}</td>

<td>

<a href="{{ route('quiz-categories.edit',$category->id) }}"
class="btn btn-warning btn-sm">

Edit

</a>

<form
action="{{ route('quiz-categories.destroy',$category->id) }}"
method="POST"
class="d-inline">

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

<td colspan="4" class="text-center">

No Categories Found

</td>

</tr>

@endforelse

</tbody>

</table>

</div>

</div>

@endsection