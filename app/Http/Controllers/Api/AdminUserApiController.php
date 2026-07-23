<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminUserApiController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(User $u) => $this->userJson($u));

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Fname'    => ['required', 'string', 'max:255'],
            'Lname'    => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'Fname'    => $validated['Fname'],
            'Lname'    => $validated['Lname'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'lecturer',
        ]);

        return response()->json(['message' => "Lecturer account created for {$user->Fname} {$user->Lname}.", 'user' => $this->userJson($user)], 201);
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

        Notification::create([
            'user_ID'            => $user->id,
            'Notification_Type'  => 'warning',
            'Notification_Title' => $user->is_blacklisted
                ? '⛔ Your account has been suspended'
                : "⚠️ Warning {$user->warnings}/2 issued to your account",
            'Message'            => $user->is_blacklisted
                ? 'You have received 2 warnings and your account has been blacklisted.'
                : 'You have received a warning from an admin.' . ($request->reason ? ' Reason: ' . $request->reason : ''),
            'Is_Read'            => false,
        ]);

        $message = $user->is_blacklisted
            ? "{$user->Fname} has been blacklisted after {$user->warnings} warnings."
            : "Warning {$user->warnings}/2 issued to {$user->Fname}.";

        return response()->json(['message' => $message, 'user' => $this->userJson($user)]);
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

        return response()->json(['message' => "{$user->Fname} has been blacklisted.", 'user' => $this->userJson($user)]);
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

        return response()->json(['message' => "{$user->Fname} has been reinstated.", 'user' => $this->userJson($user)]);
    }

    public function promote(Request $request, User $user)
    {
        $request->validate(['role' => ['required', 'in:student,lecturer,admin']]);
        $old = $user->role;
        $user->update(['role' => $request->role]);

        return response()->json(['message' => "{$user->Fname}'s role changed from {$old} to {$request->role}.", 'user' => $this->userJson($user)]);
    }

    private function userJson(User $u): array
    {
        return [
            'id'             => $u->id,
            'Fname'          => $u->Fname,
            'Lname'          => $u->Lname,
            'email'          => $u->email,
            'role'           => $u->role,
            'warnings'       => $u->warnings ?? 0,
            'is_blacklisted' => (bool) $u->is_blacklisted,
        ];
    }
}
