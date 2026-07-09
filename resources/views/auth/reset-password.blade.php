@extends('layouts.auth')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card-brand">
            <div class="auth-logo">SD</div>
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Choose a new password</p>
        </div>

        <div class="auth-card-form">
            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="mb-2">
                    <label for="email" class="form-label auth-label">Email</label>
                    <input id="email" type="email" name="email" class="form-control auth-input"
                           value="{{ old('email', $request->email) }}" required autocomplete="email">
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label auth-label">New Password</label>
                    <div class="position-relative">
                        <input id="password" type="password" name="password" class="form-control auth-input pe-5" required autocomplete="new-password">
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-2 text-muted toggle-password" data-target="password" tabindex="-1" style="border:none;background:none;">
                            <i data-feather="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label auth-label">Confirm Password</label>
                    <div class="position-relative">
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control auth-input pe-5" required autocomplete="new-password">
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-2 text-muted toggle-password" data-target="password_confirmation" tabindex="-1" style="border:none;background:none;">
                            <i data-feather="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">Reset Password</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
