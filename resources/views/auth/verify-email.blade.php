@extends('layouts.auth')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card-brand">
            <div class="auth-logo">SD</div>
            <h1 class="auth-title">Verify your email</h1>
            <p class="auth-subtitle">One more step before you get started</p>
        </div>

        <div class="auth-card-form">
            <p class="small text-muted mb-3">
                Thanks for signing up! We sent a verification link to your email address.
                Please click it to activate your account.
            </p>

            @if(session('status') == 'verification-link-sent')
                <div class="alert alert-success py-2 small mb-3">
                    A new verification link has been sent to your email address.
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary w-100 auth-submit mb-2">
                    Resend Verification Email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100 auth-submit">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
