<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of any account that is no longer allowed in (blocked, switched off, or its e-mail confirmation
 * was withdrawn). Login only checks the status once; without this, an admin blocking someone would not affect
 * a session that is already open, and a "suspended" administrator could keep working until the cookie expires.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // An unsaved model (no status attribute) is not a real account row; every stored user has a status
        if ($user instanceof User && $user->getAttribute('status') !== null && ! $user->canSignIn()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tài khoản của bạn hiện không được phép truy cập.'], 401);
            }

            return redirect()->route($request->is('admin*') ? 'admin.login' : 'login')
                ->withErrors(['email' => $user->accessDeniedMessage()]);
        }

        return $next($request);
    }
}
