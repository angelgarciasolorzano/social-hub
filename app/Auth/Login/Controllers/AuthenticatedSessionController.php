<?php

namespace App\Auth\Login\Controllers;

use App\Auth\Login\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     * 
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     * 
     * @param LoginRequest $request
     * @return RedirectResponse
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}