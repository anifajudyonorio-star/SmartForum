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
        abort_unless(Auth::user()->isAdmin(), 403);

        $users = User::where('id', '!=', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user));

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'Fname' => ['required', 'string', 'max:255'],
            'Lname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'Fname' => $validated['Fname'],
            'Lname' => $validated['Lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'lecturer',
        ]);

        return response()->json([
            'message' => "Lecturer account created for {$validated['Fname']} {$validated['Lname']}.",
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function warn(Request $request, User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
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
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'action' => $user->is_blacklisted ? 'blacklist' : 'warning',
            'reason' => $request->reason,
        ]);

        $warningNumber = $user->warnings;
        $isBlacklisted = $user->is_blacklisted;

        Notification::create([
            'user_ID' => $user->id,
            'Notification_Type' => 'warning',
            'Notification_Title' => $isBlacklisted
                ? '⛔ Your account has been suspended'
                : "⚠️ Warning {$warningNumber}/2 issued to your account",
            'Message' => $isBlacklisted
                ? 'You have received 2 warnings and your account has been blacklisted. Contact support if you believe this is a mistake.'
                : 'You have received a warning from an admin.'.($request->reason ? ' Reason: '.$request->reason : '').($warningNumber >= 1 ? ' One more warning will result in your account being suspended.' : ''),
            'Is_Read' => false,
        ]);

        return response()->json([
            'message' => $user->is_blacklisted
                ? "{$user->Fname} has been blacklisted after {$user->warnings} warnings."
                : "Warning {$user->warnings}/2 issued to {$user->Fname}.",
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function blacklist(Request $request, User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $user->update(['is_blacklisted' => true]);

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'action' => 'blacklist',
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => "{$user->Fname} has been blacklisted.",
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function unblacklist(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $user->update(['is_blacklisted' => false, 'warnings' => 0]);

        ModerationLog::create([
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'action' => 'unblacklist',
            'reason' => 'Reinstated by admin',
        ]);

        return response()->json([
            'message' => "{$user->Fname} has been reinstated.",
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function promote(Request $request, User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'role' => ['required', 'in:student,lecturer,admin'],
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => "{$user->Fname}'s role changed from {$oldRole} to {$validated['role']}.",
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim("{$user->Fname} {$user->Lname}"),
            'Fname' => $user->Fname,
            'Lname' => $user->Lname,
            'email' => $user->email,
            'role' => $user->role,
            'warnings' => (int) $user->warnings,
            'is_blacklisted' => (bool) $user->is_blacklisted,
        ];
    }
}
