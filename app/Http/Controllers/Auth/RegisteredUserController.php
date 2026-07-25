<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\EmailVerificationCodeController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.login', ['activeTab' => 'register']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'Fname' => ['required', 'string', 'max:255'],
            'Lname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'in:student'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must agree to the Terms & Conditions to create an account.',
        ]);

        $user = User::create([
            'Fname' => $request->Fname,
            'Lname' => $request->Lname,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $emailed = EmailVerificationCodeController::sendCode($user);

        $redirect = redirect()->route('verification.notice');

        if (! $emailed) {
            return $redirect->with(
                'mail_error',
                'Your account was created, but we could not send the verification email. Use Resend code after email is configured, or contact an administrator.'
            );
        }

        return $redirect->with('status', 'A verification code has been sent to your email.');
    }
}
