<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class EmailVerificationCodeController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        $record = EmailVerificationCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->latest()
            ->first();

        if (! $record || $record->isExpired()) {
            return back()->withErrors(['code' => 'The code is invalid or has expired.']);
        }

        $user->markEmailAsVerified();
        $record->delete();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $recent = EmailVerificationCode::where('user_id', $user->id)
            ->where('expires_at', '>', now()->addMinutes(9))
            ->exists();

        if ($recent) {
            return back()->with('resend_error', 'Please wait before requesting a new code.');
        }

        if (! self::sendCode($user)) {
            return back()->with('mail_error', 'We could not send the verification email. Please try again later.');
        }

        return back()->with('status', 'A new code has been sent to your email.');
    }

    /**
     * Create a verification code and email it to the user.
     *
     * @return bool true when the email was accepted by the mailer
     */
    public static function sendCode($user): bool
    {
        EmailVerificationCode::where('user_id', $user->id)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        if (! self::mailIsConfigured()) {
            return false;
        }

        try {
            Mail::to($user->email)->send(
                new VerificationCodeMail($code, $user->Fname ?? $user->email)
            );

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function mailIsConfigured(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array', ''], true)) {
            return false;
        }

        if ($mailer === 'smtp') {
            $username = (string) config('mail.mailers.smtp.username');
            $password = (string) config('mail.mailers.smtp.password');

            return filled($username)
                && filled($password)
                && ! in_array($username, ['null', 'your@gmail.com'], true)
                && ! in_array($password, ['null'], true);
        }

        return true;
    }
}
