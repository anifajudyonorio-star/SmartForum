@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title">Performance Report — {{ $quiz->title }}</h1>
        <p class="page-subtitle">Results for assigned members (after quiz end)</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['student']->name }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['score'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
