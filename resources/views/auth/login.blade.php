@extends('layouts.auth')

@section('content')
@php $activeTab = $activeTab ?? (request()->routeIs('register') ? 'register' : 'login'); @endphp

<div class="auth-page">
    <div class="auth-card auth-card-wide">
        <div class="auth-card-brand">
            <div class="auth-logo">SD</div>
            <h1 class="auth-title">Smart Discussion</h1>
            <p class="auth-subtitle">Join as a student or lecturer. One platform for learning discussions.</p>
        </div>

        <div class="auth-card-form">
            <ul class="nav nav-pills auth-tabs mb-3" role="tablist">
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100 {{ $activeTab === 'login' ? 'active' : '' }}" data-auth-tab="login" type="button">Login</button>
                </li>
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100 {{ $activeTab === 'register' ? 'active' : '' }}" data-auth-tab="register" type="button">Register</button>
                </li>
            </ul>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            {{-- Login --}}
            <div id="auth-login" class="auth-panel {{ $activeTab === 'login' ? '' : 'd-none' }}">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="login_email" class="form-label small fw-semibold">Email</label>
                        <input id="login_email" type="email" name="email" class="form-control form-control-sm"
                               value="{{ old('email') }}" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="login_password" class="form-label small fw-semibold">Password</label>
                        <input id="login_password" type="password" name="password" class="form-control form-control-sm" required autocomplete="current-password">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-sm py-2">Login</button>
                </form>

                <div class="auth-demo mt-3">
                    <p class="small text-muted mb-2">Demo accounts — password: <code>password</code></p>
                    <div class="auth-demo-list">
                        <button type="button" class="auth-demo-btn" data-email="student@smartforum.com" data-password="password">
                            <span class="auth-demo-role">Student</span>
                            <span class="auth-demo-email">student@smartforum.com</span>
                        </button>
                        <button type="button" class="auth-demo-btn" data-email="lecturer@smartforum.com" data-password="password">
                            <span class="auth-demo-role">Lecturer</span>
                            <span class="auth-demo-email">lecturer@smartforum.com</span>
                        </button>
                        <button type="button" class="auth-demo-btn" data-email="admin@smartforum.com" data-password="password">
                            <span class="auth-demo-role">Super Admin</span>
                            <span class="auth-demo-email">admin@smartforum.com</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Register --}}
            <div id="auth-register" class="auth-panel {{ $activeTab === 'register' ? '' : 'd-none' }}">
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="Fname" class="form-label small fw-semibold">First name</label>
                            <input id="Fname" type="text" name="Fname" class="form-control form-control-sm" value="{{ old('Fname') }}" required>
                        </div>
                        <div class="col-6">
                            <label for="Lname" class="form-label small fw-semibold">Last name</label>
                            <input id="Lname" type="text" name="Lname" class="form-control form-control-sm" value="{{ old('Lname') }}" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="register_email" class="form-label small fw-semibold">Email</label>
                        <input id="register_email" type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold d-block">Account type</label>
                        <div class="d-flex gap-2">
                            <label class="auth-role-option flex-fill {{ old('role', 'student') === 'student' ? 'active' : '' }}">
                                <input type="radio" name="role" value="student" {{ old('role', 'student') === 'student' ? 'checked' : '' }} required>
                                <span>Student</span>
                                <small class="d-block text-muted">Join groups & post</small>
                            </label>
                            <label class="auth-role-option flex-fill {{ old('role') === 'lecturer' ? 'active' : '' }}">
                                <input type="radio" name="role" value="lecturer" {{ old('role') === 'lecturer' ? 'checked' : '' }}>
                                <span>Lecturer</span>
                                <small class="d-block text-muted">Create groups & topics</small>
                            </label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="register_password" class="form-label small fw-semibold">Password</label>
                        <input id="register_password" type="password" name="password" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label small fw-semibold">Confirm password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-sm py-2">Create account</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-auth-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.authTab;
        document.querySelectorAll('[data-auth-tab]').forEach((t) => t.classList.toggle('active', t.dataset.authTab === target));
        document.getElementById('auth-login').classList.toggle('d-none', target !== 'login');
        document.getElementById('auth-register').classList.toggle('d-none', target !== 'register');
    });
});

document.querySelectorAll('.auth-demo-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.getElementById('login_email').value = btn.dataset.email;
        document.getElementById('login_password').value = btn.dataset.password;
        document.querySelector('[data-auth-tab="login"]').click();
    });
});

document.querySelectorAll('.auth-role-option input').forEach((input) => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.auth-role-option').forEach((opt) => opt.classList.remove('active'));
        input.closest('.auth-role-option').classList.add('active');
    });
});
</script>
@endpush
