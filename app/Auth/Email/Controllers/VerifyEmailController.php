<?php

namespace App\Auth\Email\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false).'?verified=1');
        }

        $request->fulfill();

        return redirect()->intended(route('home', absolute: false).'?verified=1');
    }
}
