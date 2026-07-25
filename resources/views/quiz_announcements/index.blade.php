@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Quiz Announcements</h1>
        <p class="page-subtitle">Share updates with students enrolled in your quiz titles.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><strong>Post Announcement</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('quiz-announcements.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Quiz Title</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select a quiz title</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                        {{ $category->category_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" maxlength="5000" required>{{ old('message') }}</textarea>
                        </div>
                        <button class="btn btn-primary w-100">Post Announcement</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Published Announcements</strong>
                    <span class="badge bg-secondary">{{ $announcements->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="responsive-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Quiz Title</th>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <th>Posted By</th>
                                        <th>Date</th>
                                        <th width="90">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($announcements as $announcement)
                                        <tr>
                                            <td>{{ $announcement->category->category_name ?? '—' }}</td>
                                            <td><strong>{{ $announcement->title }}</strong></td>
                                            <td class="small">{{ \Illuminate\Support\Str::limit($announcement->message, 120) }}</td>
                                            <td class="small">{{ $announcement->author->name ?? '—' }}</td>
                                            <td class="small">{{ $announcement->created_at?->format('M j, Y g:i A') }}</td>
                                            <td>
                                                @can('delete', $announcement)
                                                    <form method="POST" action="{{ route('quiz-announcements.destroy', $announcement) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this announcement?')">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No announcements yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="responsive-card-wrap p-2">
                        <div class="data-card-list">
                            @forelse($announcements as $announcement)
                                <div class="data-card-item">
                                    <p class="data-card-item-title">{{ $announcement->title }}</p>
                                    <p class="small text-muted mb-2">{{ $announcement->category->category_name ?? '—' }}</p>
                                    <p class="small mb-2">{{ \Illuminate\Support\Str::limit($announcement->message, 200) }}</p>
                                    <div class="data-card-item-meta">
                                        <span><i class="bi bi-person me-1"></i>{{ $announcement->author->name ?? '—' }}</span>
                                        <span><i class="bi bi-calendar me-1"></i>{{ $announcement->created_at?->format('M j, Y g:i A') }}</span>
                                    </div>
                                    @can('delete', $announcement)
                                        <div class="data-card-item-actions">
                                            <form method="POST" action="{{ route('quiz-announcements.destroy', $announcement) }}" class="w-100">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm w-100" onclick="return confirm('Delete this announcement?')">Delete</button>
                                            </form>
                                        </div>
                                    @endcan
                                </div>
                            @empty
                                <p class="text-muted small text-center py-4 mb-0">No announcements yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
