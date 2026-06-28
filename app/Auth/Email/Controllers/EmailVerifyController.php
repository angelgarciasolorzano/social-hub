<?php

declare(strict_types=1);

namespace App\Auth\Email\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class EmailVerifyController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $emailVerificationRequest): RedirectResponse
    {
        $user = $emailVerificationRequest->user();

        abort_unless($user instanceof User, 401);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false).'?verified=1');
        }

        $emailVerificationRequest->fulfill();

        return redirect()->intended(route('home', absolute: false).'?verified=1');
    }
}
