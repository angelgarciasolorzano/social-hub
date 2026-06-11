<?php

declare(strict_types=1);

namespace App\Auth\Email\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Show the email verification prompt page.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403, 'Unauthorized');

        return $user->hasVerifiedEmail()
            ? redirect()->intended(route('home', absolute: false))
            : Inertia::render('auth/VerifyEmail', ['status' => $request->session()->get('status')]);
    }
}
