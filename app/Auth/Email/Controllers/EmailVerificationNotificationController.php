<?php

declare(strict_types=1);

namespace App\Auth\Email\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('home', absolute: false));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
