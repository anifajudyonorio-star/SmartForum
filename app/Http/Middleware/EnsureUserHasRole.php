<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        foreach ($roles as $role) {
            if ($role === 'admin' && $user->isAdmin()) {
                return $next($request);
            }

            if ($role === 'lecturer' && ($user->isLecturer() || $user->isAdmin())) {
                return $next($request);
            }

            if ($role === 'student' && $user->isStudent()) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
