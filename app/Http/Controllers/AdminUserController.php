<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ModerationLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', Auth::id())->orderBy('created_at', 'desc')->get();
        return view('admin.users', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Fname'    => ['required', 'string', 'max:255'],
            'Lname'    => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'Fname'    => $validated['Fname'],
            'Lname'    => $validated['Lname'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'lecturer',
        ]);

        return back()->with('success', "Lecturer account created for {$validated['Fname']} {$validated['Lname']}.");
    }

    public function warn(Request $request, User $user)
    {
        if ($user->warnings >= 2) {
            $user->is_blacklisted = true;
        } else {
            $user->warnings += 1;
            if ($user->warnings >= 2) {
                $user->is_blacklisted = true;
            }
        }
        $user->save();

        ModerationLog::create([
            'user_id'  => $user->id,
            'admin_id' => Auth::id(),
            'action'   => $user->is_blacklisted ? 'blacklist' : 'warning',
            'reason'   => $request->reason,
        ]);

        // Notify the user
        $warningNumber = $user->warnings;
        $isBlacklisted = $user->is_blacklisted;

        Notification::create([
            'user_ID'            => $user->id,
            'Notification_Type'  => 'warning',
            'Notification_Title' => $isBlacklisted
                ? '⛔ Your account has been suspended'
                : "⚠️ Warning {$warningNumber}/2 issued to your account",
            'Message'            => $isBlacklisted
                ? 'You have received 2 warnings and your account has been blacklisted. Contact support if you believe this is a mistake.'
                : 'You have received a warning from an admin.' . ($request->reason ? ' Reason: ' . $request->reason : '') . ($warningNumber >= 1 ? ' One more warning will result in your account being suspended.' : ''),
            'Is_Read'            => false,
        ]);

        return back()->with('success', $user->is_blacklisted
            ? "{$user->Fname} has been blacklisted after {$user->warnings} warnings."
            : "Warning {$user->warnings}/2 issued to {$user->Fname}.");
    }

    public function blacklist(Request $request, User $user)
    {
        $user->update(['is_blacklisted' => true]);

        ModerationLog::create([
            'user_id'  => $user->id,
            'admin_id' => Auth::id(),
            'action'   => 'blacklist',
            'reason'   => $request->reason,
        ]);

        return back()->with('success', "{$user->Fname} has been blacklisted.");
    }

    public function unblacklist(User $user)
    {
        $user->update(['is_blacklisted' => false, 'warnings' => 0]);

        ModerationLog::create([
            'user_id'  => $user->id,
            'admin_id' => Auth::id(),
            'action'   => 'unblacklist',
            'reason'   => 'Reinstated by admin',
        ]);

        return back()->with('success', "{$user->Fname} has been reinstated.");
    }
}
