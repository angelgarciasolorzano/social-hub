<?php

declare(strict_types=1);

namespace App\Auth\Modules\Login\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\Login\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class LoginSessionController extends Controller
{
    /**
     * Show the login page.
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
     */
    public function store(LoginRequest $loginRequest): RedirectResponse
    {
        $user = $loginRequest->validateCredentials();
        $this->forgetAdminIntendedUrl($loginRequest);

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            if (TrustedDevice::validateAndTouch($user, $loginRequest)) {
                Auth::login($user, $loginRequest->boolean('remember'));

                $loginRequest->session()->regenerate();

                return redirect()->intended(route('home', absolute: false));
            }

            $loginRequest->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $loginRequest->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $loginRequest->boolean('remember'));

        $loginRequest->session()->regenerate();

        return redirect()->intended(route('home', absolute: false));
    }

    private function forgetAdminIntendedUrl(LoginRequest $loginRequest): void
    {
        $intendedUrl = $loginRequest->session()->get('url.intended');

        if (! \is_string($intendedUrl)) {
            return;
        }

        $intendedPath = parse_url($intendedUrl, PHP_URL_PATH);

        if (! \is_string($intendedPath)) {
            return;
        }

        if ($intendedPath === '/admin' || str_starts_with($intendedPath, '/admin/')) {
            $loginRequest->session()->forget('url.intended');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
