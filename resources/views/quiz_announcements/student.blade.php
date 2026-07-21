@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2">
        <div>
            <h1 class="page-title"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Quiz Announcements</h1>
            <p class="page-subtitle mb-0">
                @if($enrolledCategory)
                    Updates for your enrolled quiz title: <strong>{{ $enrolledCategory->category_name }}</strong>.
                @else
                    Enroll in a quiz title to receive announcements.
                @endif
            </p>
        </div>
        <a href="{{ route('student.quizzes') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-patch-question me-1"></i>Available Quizzes
        </a>
    </div>

    @if(!$enrolledCategory)
        <div class="alert alert-info">
            You are not enrolled in a quiz title yet.
            <a href="{{ route('student.quizzes') }}">Enroll now</a> to see lecturer announcements.
        </div>
    @endif

    <div class="row g-3">
        @forelse($announcements as $announcement)
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <strong>{{ $announcement->title }}</strong>
                        <div class="small text-muted">
                            {{ $announcement->category->category_name ?? '—' }} ·
                            {{ $announcement->author->name ?? 'Lecturer' }} ·
                            {{ $announcement->created_at?->format('M j, Y g:i A') }}
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-wrap;">{{ $announcement->message }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-4">
                        {{ $enrolledCategory ? 'No announcements have been posted for your quiz title yet.' : 'No announcements to show.' }}
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
