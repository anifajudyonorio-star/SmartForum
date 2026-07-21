@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="page-header fly-in d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2">
        <div>
            <h1 class="page-title"><i class="bi bi-patch-question-fill me-2 text-primary"></i>Available Quizzes</h1>
            <p class="page-subtitle mb-0">Enroll in a quiz title, then take quizzes within their active window. Each quiz can only be taken once.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('student.announcements') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-megaphone me-1"></i>Announcements
            </a>
            <a href="{{ route('student.quizzes.progress') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-graph-up-arrow me-1"></i>My Quiz Progress
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Quiz Title Enrollment</strong></div>
        <div class="card-body">
            @if($enrolledCategory)
                <p class="mb-2">
                    You are enrolled in <strong>{{ $enrolledCategory->category_name }}</strong>.
                    Only quizzes under this title are shown below.
                </p>
                <form method="POST" action="{{ route('student.quizzes.unenroll') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Unenroll from this quiz title?')">Unenroll</button>
                </form>
            @else
                <p class="mb-3 text-muted">You must enroll in one quiz title before you can take quizzes.</p>
                <form method="POST" action="{{ route('student.quizzes.enroll') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label">Select quiz title</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Choose a quiz title</option>
                            @foreach($availableCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100">Enroll Me</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="responsive-table-wrap">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Questions</th>
                                <th>Maximum Marks</th>
                                <th>Scheduled</th>
                                <th>Duration</th>
                                <th>Ends At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quizzes as $quiz)
                                <tr>
                                    <td>
                                        <strong>{{ $quiz->title }}</strong>
                                        <div class="small text-muted">{{ $quiz->description }}</div>
                                    </td>
                                    <td>{{ $quiz->questions_count }}</td>
                                    <td>{{ $quiz->authoredMaximumTotal() }}</td>
                                    <td>{{ $quiz->start_time->format('M j, Y g:i A') }}</td>
                                    <td>{{ $quiz->duration }} min</td>
                                    <td>{{ $quiz->end_time->format('M j, Y g:i A') }}</td>
                                    <td>
                                        @if($completedQuizIds->contains($quiz->id))
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($quiz->lifecycleStatus() === \App\Models\Quiz::STATUS_SCHEDULED)
                                            <span class="badge bg-warning text-dark">Upcoming</span>
                                        @else
                                            <span class="badge bg-primary">Available</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($completedQuizIds->contains($quiz->id))
                                            <span class="text-muted small">Already taken</span>
                                        @elseif($quiz->lifecycleStatus() === \App\Models\Quiz::STATUS_SCHEDULED)
                                            <span class="text-muted small">Not open yet</span>
                                        @else
                                            <a href="{{ route('student.quiz.show', $quiz) }}" class="btn btn-primary btn-sm">Start Quiz</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        {{ $enrolledCategory ? 'No quizzes are available right now.' : 'Enroll in a quiz title to see available quizzes.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="responsive-card-wrap">
        <div class="data-card-list">
            @forelse($quizzes as $quiz)
                <div class="data-card-item fly-in">
                    <p class="data-card-item-title">{{ $quiz->title }}</p>
                    <p class="small text-muted mb-2">{{ $quiz->description }}</p>
                    <div class="data-card-item-meta">
                        <span><i class="bi bi-question-circle me-1"></i>{{ $quiz->questions_count }} questions</span>
                        <span><i class="bi bi-award me-1"></i>{{ $quiz->authoredMaximumTotal() }} maximum marks</span>
                        <span><i class="bi bi-calendar2-event me-1"></i>Scheduled: {{ $quiz->start_time->format('M j, Y g:i A') }}</span>
                        <span><i class="bi bi-clock me-1"></i>{{ $quiz->duration }} min</span>
                        <span><i class="bi bi-calendar me-1"></i>Ends: {{ $quiz->end_time->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="data-card-item-actions">
                        @if($completedQuizIds->contains($quiz->id))
                            <span class="badge bg-success">Completed</span>
                        @elseif($quiz->lifecycleStatus() === \App\Models\Quiz::STATUS_SCHEDULED)
                            <span class="badge bg-warning text-dark">Coming Soon</span>
                        @else
                            <a href="{{ route('student.quiz.show', $quiz) }}" class="btn btn-primary btn-sm w-100">Start Quiz</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="groups-empty-state">
                    <div class="groups-empty-icon"><i class="bi bi-patch-question"></i></div>
                    <p class="text-muted mb-0">
                        {{ $enrolledCategory ? 'No quizzes available right now.' : 'Enroll in a quiz title to see available quizzes.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
