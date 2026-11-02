<?php

declare(strict_types=1);

namespace App\Auth\Modules\Register\Controllers;

use App\Auth\Modules\Register\Requests\RegisterRequest;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetApplicationLocale;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisterUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register/Register');
    }

    /**
     * Handle an incoming registration request. The account keeps the language SetApplicationLocale resolved
     * for the guest (the browser's, or Spanish), so a new user does not fall back to the column's English default.
     */
    public function store(RegisterRequest $registerRequest): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $registerRequest->name,
            'email' => $registerRequest->email,
            'password' => Hash::make($registerRequest->password),
            'preferred_locale' => PreferredLocale::tryFrom(app()->getLocale()) ?? SetApplicationLocale::GUEST_FALLBACK_LOCALE,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('home', absolute: false));
    }
}
