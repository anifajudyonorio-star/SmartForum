<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'OAuth authentication failed. Please try again.']);
        }

        $field = $provider . '_id';
        $name  = $social->getName() ?: $social->getNickname() ?: '';
        $parts = explode(' ', trim($name), 2);

        // Find by OAuth id first, then by email
        $user = User::where($field, $social->getId())->first()
            ?? User::where('email', $social->getEmail())->first();

        if ($user) {
            // Link OAuth id if not yet linked
            if (! $user->$field) {
                $user->update([$field => $social->getId()]);
            }
        } else {
            $user = User::create([
                $field     => $social->getId(),
                'Fname'    => $parts[0] ?? 'User',
                'Lname'    => $parts[1] ?? '',
                'email'    => $social->getEmail(),
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                'role'     => 'student',
            ]);
        }

        if ($user->is_blacklisted) {
            return redirect()->route('login')->withErrors(['email' => 'Your account has been suspended.']);
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
