<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class DirectPasswordResetController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.exists' => 'No account found with that email address.',
        ]);

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);

        return redirect()->route('login')
            ->with('status', 'Password reset successfully. You can now log in.');
    }
}
