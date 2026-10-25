<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * End the session of an account suspended while it was signed in, including one
 * restored from a remember-me cookie. Guests and active accounts pass through.
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request for the given guard, sending a suspended account to that guard's login.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $guard = 'web', string $loginRoute = 'login'): Response
    {
        $authGuard = Auth::guard($guard);
        $user = $authGuard->user();

        if (! $user instanceof User || ! $user->isSuspended()) {
            return $next($request);
        }

        $authGuard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => AccountStatus::SUSPENDED_LOGIN_MESSAGE], Response::HTTP_FORBIDDEN);
        }

        return to_route($loginRoute)->withErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);
    }
}
