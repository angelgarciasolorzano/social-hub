<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    private const SESSION_KEY_PASSWORD_CONFIRMATION_REDIRECT = 'auth.password_confirmation_redirect';

    private const SESSION_KEY_PASSWORD_CONFIRMED_AT = 'auth.password_confirmed_at';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(function (Request $request) {
            if ($this->hasValidPasswordConfirmation($request)) {
                return redirect()->to($this->passwordConfirmationRedirectTo($request));
            }

            return Inertia::render('auth/password/ConfirmPassword');
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        $this->app->singleton(PasswordConfirmedResponse::class, function () {
            return new class implements PasswordConfirmedResponse
            {
                public function toResponse($request)
                {
                    $request->session()->put(
                        'auth.password_confirmation_redirect',
                        $request->session()->get('url.intended', route('home', absolute: false))
                    );

                    return redirect()->intended(route('home', absolute: false));
                }
            };
        });
    }

    private function hasValidPasswordConfirmation(Request $request): bool
    {
        $confirmedAt = $request->session()->get(self::SESSION_KEY_PASSWORD_CONFIRMED_AT);

        return \is_int($confirmedAt) && time() - $confirmedAt < config('auth.password_timeout', 10800);
    }

    private function passwordConfirmationRedirectTo(Request $request): string
    {
        /** @var string $passwordConfirmationRedirect */
        $passwordConfirmationRedirect = $request->session()->get(
            self::SESSION_KEY_PASSWORD_CONFIRMATION_REDIRECT,
            $request->session()->get('url.intended', route('home', absolute: false))
        );

        return $passwordConfirmationRedirect;
    }
}
