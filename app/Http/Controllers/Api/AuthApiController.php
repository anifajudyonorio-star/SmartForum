<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
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

        $token = $user->createToken('desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ], 201);
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
