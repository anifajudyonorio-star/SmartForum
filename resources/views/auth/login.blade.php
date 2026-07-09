@extends('layouts.auth')

@section('content')
@php
    $registerFields = ['Fname', 'Lname', 'password', 'terms'];
    $hasRegisterError = collect($registerFields)->contains(fn($f) => $errors->has($f));
    $activeTab = $activeTab ?? ($hasRegisterError ? 'register' : (request()->routeIs('register') ? 'register' : 'login'));
@endphp

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card-brand">
            <div class="auth-logo">SD</div>
            <h1 class="auth-title">Smart Discussion</h1>
            <p class="auth-subtitle">Sign in to continue</p>
        </div>

        <div class="auth-card-form">
            <ul class="nav nav-pills auth-tabs mb-2" role="tablist">
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100 {{ $activeTab === 'login' ? 'active' : '' }}" data-auth-tab="login" type="button">Sign In</button>
                </li>
                <li class="nav-item flex-fill">
                    <button class="nav-link w-100 {{ $activeTab === 'register' ? 'active' : '' }}" data-auth-tab="register" type="button">Register</button>
                </li>
            </ul>

            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            {{-- Login --}}
            <div id="auth-login" class="auth-panel {{ $activeTab === 'login' ? '' : 'd-none' }}">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-2">
                        <label for="login_email" class="form-label auth-label">Email</label>
                        <input id="login_email" type="email" name="email" class="form-control auth-input"
                               value="{{ old('email') }}" required autocomplete="email">
                    </div>
                    <div class="mb-2">
                        <label for="login_password" class="form-label auth-label">Password</label>
                        <div class="position-relative">
                            <input id="login_password" type="password" name="password" class="form-control auth-input pe-5" required autocomplete="current-password">
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-2 text-muted toggle-password" data-target="login_password" tabindex="-1" style="border:none;background:none;">
                                <i data-feather="eye" style="width:16px;height:16px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label auth-label" for="remember">Remember me</label>
                        </div>
                        <a href="{{ route('password.request') }}" id="forgot-link" class="auth-label text-primary" style="font-size:0.75rem;">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 auth-submit">Login</button>
                </form>
                <div class="auth-divider my-3"><span>or</span></div>
                <a href="{{ route('auth.social.redirect', 'google') }}" class="btn btn-outline-secondary w-100 mb-2 d-flex align-items-center justify-content-center gap-2">
                    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Continue with Google
                </a>
            </div>

            {{-- Register --}}
            <div id="auth-register" class="auth-panel {{ $activeTab === 'register' ? '' : 'd-none' }}">
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="Fname" class="form-label auth-label">First name</label>
                            <input id="Fname" type="text" name="Fname" class="form-control auth-input" value="{{ old('Fname') }}" required>
                        </div>
                        <div class="col-6">
                            <label for="Lname" class="form-label auth-label">Last name</label>
                            <input id="Lname" type="text" name="Lname" class="form-control auth-input" value="{{ old('Lname') }}" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="register_email" class="form-label auth-label">Email</label>
                        <input id="register_email" type="email" name="email" class="form-control auth-input" value="{{ old('email') }}" required>
                    </div>
                    <input type="hidden" name="role" value="student">
                    <div class="mb-2">
                        <label for="register_password" class="form-label auth-label">Password</label>
                        <div class="position-relative">
                            <input id="register_password" type="password" name="password" class="form-control auth-input pe-5" required>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-2 text-muted toggle-password" data-target="register_password" tabindex="-1" style="border:none;background:none;">
                                <i data-feather="eye" style="width:16px;height:16px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="password_confirmation" class="form-label auth-label">Confirm password</label>
                        <div class="position-relative">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control auth-input pe-5" required>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-2 text-muted toggle-password" data-target="password_confirmation" tabindex="-1" style="border:none;background:none;">
                                <i data-feather="eye" style="width:16px;height:16px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" name="terms" id="terms" class="form-check-input" required>
                        <label for="terms" class="form-check-label auth-label">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-primary">Terms & Conditions</a>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 auth-submit">Create account</button>
                </form>
                <div class="auth-divider my-3"><span>or</span></div>
                <a href="{{ route('auth.social.redirect', 'google') }}" class="btn btn-outline-secondary w-100 mb-2 d-flex align-items-center justify-content-center gap-2">
                    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Continue with Google
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Terms & Conditions</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small text-muted" style="line-height:1.7">
                <p><strong>1. Acceptable Use</strong><br>
                SmartForum is an academic discussion platform. You agree to use it only for lawful, educational purposes.</p>
                <p><strong>2. Respectful Conduct</strong><br>
                You must treat all users with respect. Harassment, hate speech, or abusive behaviour will result in warnings or account suspension.</p>
                <p><strong>3. Account Responsibility</strong><br>
                You are responsible for keeping your login credentials secure. Do not share your account with others.</p>
                <p><strong>4. Content</strong><br>
                Do not post spam, plagiarised content, or anything unrelated to academic discussion.</p>
                <p><strong>5. Moderation</strong><br>
                Admins reserve the right to warn or suspend accounts that violate these terms.</p>
                <p><strong>6. Privacy</strong><br>
                Your data is used solely to operate the platform and will not be shared with third parties.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">I understand</button>
            </div>
        </div>
    </div>
</div>
<script>
// Forgot password link — require email first
const loginEmail = document.getElementById('login_email');
const forgotLink = document.getElementById('forgot-link');
if (loginEmail && forgotLink) {
    const baseUrl = forgotLink.href;
    function updateForgotLink() {
        const email = loginEmail.value.trim();
        if (email) {
            forgotLink.href = baseUrl + '?email=' + encodeURIComponent(email);
            forgotLink.style.opacity = '1';
            forgotLink.style.pointerEvents = 'auto';
        } else {
            forgotLink.href = '#';
            forgotLink.style.opacity = '0.4';
            forgotLink.style.pointerEvents = 'none';
        }
    }
    updateForgotLink();
    loginEmail.addEventListener('input', updateForgotLink);
}

document.querySelectorAll('[data-auth-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.authTab;
        document.querySelectorAll('[data-auth-tab]').forEach((t) => t.classList.toggle('active', t.dataset.authTab === target));
        document.getElementById('auth-login').classList.toggle('d-none', target !== 'login');
        document.getElementById('auth-register').classList.toggle('d-none', target !== 'register');
    });
});

document.querySelectorAll('.toggle-password').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.querySelector('i').setAttribute('data-feather', isPassword ? 'eye-off' : 'eye');
        feather.replace();
    });
});
</script>
@endpush
