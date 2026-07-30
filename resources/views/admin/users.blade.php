@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex align-items-center justify-content-between dashboard-header">
        <div class="fly-in">
            <h2 class="dashboard-title mb-0">👥 User Management</h2>
            <p class="text-muted small mb-0">Issue warnings and manage blacklisted users.</p>
        </div>
        <button class="btn btn-primary btn-sm" id="toggleAddLecturer">＋ Add Lecturer</button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-2 py-2 small" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Add Lecturer inline panel --}}
    <div id="addLecturerPanel" class="um-add-panel mt-3 {{ session('lecturer_errors') || $errors->any() ? '' : 'd-none' }}">
        <p class="fw-semibold mb-3" style="font-size:0.9rem;">Add Lecturer Account</p>
        @if(session('lecturer_errors'))
            <div class="alert alert-danger py-2 small">{{ session('lecturer_errors') }}</div>
        @endif
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small fw-semibold">First Name</label>
                    <input type="text" name="Fname" class="form-control form-control-sm" value="{{ old('Fname') }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Last Name</label>
                    <input type="text" name="Lname" class="form-control form-control-sm" value="{{ old('Lname') }}" required>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold">Email</label>
                <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelAddLecturer">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Lecturer</button>
            </div>
        </form>
    </div>

    {{-- User cards --}}
    <div class="mt-3 d-flex flex-column gap-3">
        @forelse($users as $user)
        <div class="um-user-card {{ $user->is_blacklisted ? 'um-user-card-blacklisted' : '' }}">
            {{-- Top row --}}
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="um-avatar">{{ strtoupper(substr($user->Fname, 0, 1)) }}</div>
                <div class="flex-grow-1 min-width-0">
                    <div class="um-user-name">{{ $user->Fname }} {{ $user->Lname }}</div>
                    <div class="um-user-email">{{ $user->email }}</div>
                </div>
            </div>

            {{-- Badges --}}
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="um-badge um-badge-role">{{ ucfirst($user->role) }}</span>
                <span class="um-badge {{ $user->warnings >= 2 ? 'um-badge-danger' : ($user->warnings == 1 ? 'um-badge-warning' : 'um-badge-muted') }}">
                    {{ $user->warnings }}/2 warns
                </span>
                <span class="um-badge {{ $user->is_blacklisted ? 'um-badge-danger' : 'um-badge-success' }}">
                    {{ $user->is_blacklisted ? 'Blacklisted' : 'Active' }}
                </span>
            </div>

            {{-- Actions --}}
            <div class="d-flex flex-wrap gap-2">
                @if(!$user->is_blacklisted)
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#warnModal{{ $user->id }}">⚠ Warn</button>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#blacklistModal{{ $user->id }}">⛔ Blacklist</button>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#promoteModal{{ $user->id }}">🛡 Role</button>
                @else
                    <form action="{{ route('admin.users.unblacklist', $user) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-success btn-sm">✔ Reinstate</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Warn Modal --}}
        <div class="modal fade" id="warnModal{{ $user->id }}" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <form action="{{ route('admin.users.warn', $user) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title">Warn {{ $user->Fname }} <small class="text-muted">({{ $user->warnings }}/2)</small></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @if($user->warnings == 1)
                                <div class="um-dialog-warning mb-2">⚠ Final warning — user will be blacklisted.</div>
                            @endif
                            <label class="form-label small fw-semibold">Reason (optional)</label>
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
                            <div class="um-dialog-warning mb-2">This will immediately block the user from accessing the platform.</div>
                            <label class="form-label small fw-semibold">Reason (optional)</label>
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

        {{-- Role Modal --}}
        <div class="modal fade" id="promoteModal{{ $user->id }}" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <form action="{{ route('admin.users.promote', $user) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title">Change Role — {{ $user->Fname }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small fw-semibold">Select new role</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="student" {{ $user->role === 'student' ? 'selected' : '' }}>Student</option>
                                <option value="lecturer" {{ $user->role === 'lecturer' ? 'selected' : '' }}>Lecturer</option>
                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @empty
        <div class="text-muted text-center py-4 small">No users found.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
    const panel = document.getElementById('addLecturerPanel');
    document.getElementById('toggleAddLecturer').addEventListener('click', () => panel.classList.toggle('d-none'));
    document.getElementById('cancelAddLecturer')?.addEventListener('click', () => panel.classList.add('d-none'));
</script>
@endpush
