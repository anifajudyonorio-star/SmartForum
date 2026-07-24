<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\EmailVerificationCodeController;
use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        if ($user->is_blacklisted) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        $token = $user->createToken('desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'Fname'    => ['required', 'string', 'max:255'],
            'Lname'    => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            'role'     => ['required', 'in:student'],
            'terms'    => ['accepted'],
        ], [
            'terms.accepted' => 'You must agree to the Terms & Conditions.',
            'email.unique'   => 'An account with this email already exists.',
        ]);

        $user = User::create([
            'Fname'    => $request->Fname,
            'Lname'    => $request->Lname,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'student',
        ]);

        EmailVerificationCodeController::sendCode($user);

        $token = $user->createToken('desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ], 201);
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        $record = EmailVerificationCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->latest()
            ->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['message' => 'The code is invalid or has expired.'], 422);
        }

        $user->markEmailAsVerified();
        $record->delete();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendCode(Request $request)
    {
        $user = $request->user();

        $recent = EmailVerificationCode::where('user_id', $user->id)
            ->where('expires_at', '>', now()->addMinutes(9))
            ->exists();

        if ($recent) {
            return response()->json(['message' => 'Please wait before requesting a new code.'], 429);
        }

        EmailVerificationCodeController::sendCode($user);

        return response()->json(['message' => 'A new code has been sent to your email.']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'    => $user->id,
            'Fname' => $user->Fname,
            'Lname' => $user->Lname,
            'email' => $user->email,
            'role'  => $user->role,
            'can_view_statistics' => $user->canViewStatistics(),
            'can_view_participation' => $user->canViewParticipation(),
            'administers_groups' => $user->administeredGroups()->exists(),
            'administered_groups_count' => $user->administeredGroups()->count(),
        ];
    }
}
