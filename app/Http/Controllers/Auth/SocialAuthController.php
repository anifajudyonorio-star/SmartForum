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
<<<<<<< Updated upstream
=======
        // Store desktop flag in session so callback knows to return a token page
>>>>>>> Stashed changes
        if (request()->query('desktop')) {
            session(['oauth_desktop' => true]);
        }
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

        $user = User::where($field, $social->getId())->first()
            ?? User::where('email', $social->getEmail())->first();

        if ($user) {
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

<<<<<<< Updated upstream
        // Desktop client — redirect to local server with token and user data, no copy-paste needed
        if (session()->pull('oauth_desktop')) {
            $token = $user->createToken('desktop-google')->plainTextToken;
            $params = http_build_query([
                'token' => $token,
                'id'    => $user->id,
                'fname' => $user->Fname,
                'lname' => $user->Lname,
                'email' => $user->email,
                'role'  => $user->role,
            ]);
            return redirect('http://localhost:9876?' . $params);
=======
        // Desktop client flow — return a Sanctum token on a simple page
        if (session()->pull('oauth_desktop')) {
            $token = $user->createToken('desktop-google')->plainTextToken;
            return view('auth.desktop-token', compact('token'));
>>>>>>> Stashed changes
        }

        return redirect()->intended(route('dashboard'));
    }
}
