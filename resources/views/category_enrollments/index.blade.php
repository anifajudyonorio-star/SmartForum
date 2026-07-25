@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-person-check-fill me-2 text-primary"></i>Category Enrollment</h1>
        <p class="page-subtitle">Enroll students into quiz titles and review membership.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header"><strong>Enroll Student</strong></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('category-enrollments.index') }}" class="mb-3">
                        <label class="form-label">Quiz Title</label>
                        <select name="category_id" class="form-select" onchange="this.form.submit()">
                            @forelse($categories as $category)
                                <option value="{{ $category->id }}" @selected(optional($selectedCategory)->id === $category->id)>
                                    {{ $category->category_name }}
                                </option>
                            @empty
                                <option value="">No quiz titles available</option>
                            @endforelse
                        </select>
                    </form>

                    @if($selectedCategory)
                        <form method="POST" action="{{ route('category-enrollments.store') }}">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $selectedCategory->id }}">
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Select a student</option>
                                    @foreach($eligibleStudents as $student)
                                        <option value="{{ $student->id }}">{{ $student->name }} ({{ $student->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary w-100">Enroll</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header"><strong>Find Student's Quiz Title</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('category-enrollments.lookup') }}">
                        @csrf
                        <input type="hidden" name="category_id" value="{{ optional($selectedCategory)->id }}">
                        <div class="mb-3">
                            <label class="form-label">Student email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <button class="btn btn-outline-secondary w-100">Find Enrollment</button>
                    </form>
                    @if($lookupResult)
                        <p class="mt-3 mb-0 small text-muted">{{ $lookupResult }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Enrolled Students</strong>
                    <span class="badge bg-secondary">{{ $enrolledStudents->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="responsive-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Email</th>
                                        <th width="110">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($enrolledStudents as $student)
                                        <tr>
                                            <td>{{ $student->name }}</td>
                                            <td>{{ $student->email }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('category-enrollments.destroy') }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="category_id" value="{{ optional($selectedCategory)->id }}">
                                                    <input type="hidden" name="user_id" value="{{ $student->id }}">
                                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Unenroll this student?')">Unenroll</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">
                                                {{ $selectedCategory ? 'No students enrolled yet.' : 'Create a quiz title first.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="responsive-card-wrap p-2">
                        <div class="data-card-list">
                            @forelse($enrolledStudents as $student)
                                <div class="data-card-item">
                                    <p class="data-card-item-title">{{ $student->name }}</p>
                                    <p class="small text-muted mb-2">{{ $student->email }}</p>
                                    <div class="data-card-item-actions">
                                        <form method="POST" action="{{ route('category-enrollments.destroy') }}" class="w-100">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="category_id" value="{{ optional($selectedCategory)->id }}">
                                            <input type="hidden" name="user_id" value="{{ $student->id }}">
                                            <button class="btn btn-danger btn-sm w-100" onclick="return confirm('Unenroll this student?')">Unenroll</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted small text-center py-4 mb-0">
                                    {{ $selectedCategory ? 'No students enrolled yet.' : 'Create a quiz title first.' }}
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
