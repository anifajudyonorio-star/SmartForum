@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between dashboard-header">
        <div class="fly-in">
            <h2 class="dashboard-title mb-0">
                <i data-feather="users" class="feather-icon me-1"></i> User Management
            </h2>
            <p class="text-muted small mb-0">Issue warnings and manage blacklisted users.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
            <i data-feather="user-plus" style="width:14px;height:14px;"></i> Add Lecturer
        </button>
    </div>

    {{-- Add Lecturer Modal --}}
    <div class="modal fade" id="addLecturerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Add Lecturer Account</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if(session('lecturer_errors'))
                            <div class="alert alert-danger py-2 small">{{ session('lecturer_errors') }}</div>
                        @endif
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small">First Name</label>
                                <input type="text" name="Fname" class="form-control form-control-sm" value="{{ old('Fname') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Last Name</label>
                                <input type="text" name="Lname" class="form-control form-control-sm" value="{{ old('Lname') }}" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Lecturer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card dashboard-card mt-3">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Warnings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr class="{{ $user->is_blacklisted ? 'table-danger' : '' }}">
                        <td>{{ $user->Fname }} {{ $user->Lname }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge bg-secondary">{{ $user->role }}</span></td>
                        <td>
                            <span class="badge {{ $user->warnings >= 2 ? 'bg-danger' : ($user->warnings == 1 ? 'bg-warning text-dark' : 'bg-light text-dark') }}">
                                {{ $user->warnings }}/2
                            </span>
                        </td>
                        <td>
                            @if($user->is_blacklisted)
                                <span class="badge bg-danger">Blacklisted</span>
                            @else
                                <span class="badge bg-success">Active</span>
                            @endif
                        </td>
                        <td>
                            @if(!$user->is_blacklisted)
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#warnModal{{ $user->id }}">
                                    <i data-feather="alert-triangle" style="width:13px;height:13px;"></i> Warn
                                </button>
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#blacklistModal{{ $user->id }}">
                                    <i data-feather="slash" style="width:13px;height:13px;"></i> Blacklist
                                </button>
                            @else
                                <form action="{{ route('admin.users.unblacklist', $user) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <i data-feather="check-circle" style="width:13px;height:13px;"></i> Reinstate
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    {{-- Warn Modal --}}
                    <div class="modal fade" id="warnModal{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <form action="{{ route('admin.users.warn', $user) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h6 class="modal-title">Warn {{ $user->Fname }}
                                            <small class="text-muted">({{ $user->warnings }}/2 warnings)</small>
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        @if($user->warnings == 1)
                                            <div class="alert alert-warning small py-2">⚠️ This is the final warning. The user will be blacklisted.</div>
                                        @endif
                                        <label class="form-label small">Reason (optional)</label>
                                        <textarea name="reason" class="form-control form-control-sm" rows="2"></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning btn-sm">Issue Warning</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Blacklist Modal --}}
                    <div class="modal fade" id="blacklistModal{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <form action="{{ route('admin.users.blacklist', $user) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h6 class="modal-title">Blacklist {{ $user->Fname }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-danger small py-2">This will immediately block the user from accessing the platform.</div>
                                        <label class="form-label small">Reason (optional)</label>
                                        <textarea name="reason" class="form-control form-control-sm" rows="2"></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger btn-sm">Blacklist User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>feather.replace();</script>
@endpush
