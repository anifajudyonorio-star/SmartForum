@extends('layouts.app')

@section('content')

<div class="container-fluid px-0">

    <div class="page-header fly-in">
        <h1 class="page-title"><i class="bi bi-person-circle me-2 text-primary"></i>Profile</h1>
        <p class="page-subtitle">Manage your account information and security settings.</p>
    </div>

    @if (session('status') === 'profile-updated')
        <div class="alert alert-success fly-in">Profile updated successfully.</div>
    @endif

    @if (session('status') === 'password-updated')
        <div class="alert alert-success fly-in">Password updated successfully.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="profile-section fly-in">
                <h2 class="profile-section-title">Profile Information</h2>
                <p class="profile-section-desc">Update your name and email address.</p>

                <form method="post" action="{{ route('profile.update') }}">
                    @csrf
                    @method('patch')

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="{{ old('name', $user->name) }}" required autocomplete="name">
                        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="{{ old('email', $user->email) }}" required autocomplete="username">
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="profile-section fly-in fly-in-delay-1">
                <h2 class="profile-section-title">Update Password</h2>
                <p class="profile-section-desc">Use a strong, unique password for your account.</p>

                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password"
                               name="current_password" autocomplete="current-password">
                        @if($errors->updatePassword->has('current_password'))
                            <div class="text-danger small mt-1">{{ $errors->updatePassword->first('current_password') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password"
                               name="password" autocomplete="new-password">
                        @if($errors->updatePassword->has('password'))
                            <div class="text-danger small mt-1">{{ $errors->updatePassword->first('password') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="password_confirmation"
                               name="password_confirmation" autocomplete="new-password">
                        @if($errors->updatePassword->has('password_confirmation'))
                            <div class="text-danger small mt-1">{{ $errors->updatePassword->first('password_confirmation') }}</div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
                </form>
            </div>

            <div class="profile-section fly-in fly-in-delay-2 border-danger">
                <h2 class="profile-section-title text-danger">Delete Account</h2>
                <p class="profile-section-desc">Permanently delete your account and all associated data.</p>

                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    Delete Account
                </button>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Enter your password to confirm permanent account deletion.</p>
                    <label for="delete_password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="delete_password" name="password" required>
                    @if($errors->userDeletion->has('password'))
                        <div class="text-danger small mt-1">{{ $errors->userDeletion->first('password') }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->userDeletion->isNotEmpty())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('deleteAccountModal')).show();
    });
</script>
@endif

@endsection
