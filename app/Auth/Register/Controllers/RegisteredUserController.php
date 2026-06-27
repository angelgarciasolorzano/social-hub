<?php

declare(strict_types=1);

namespace App\Auth\Register\Controllers;

use App\Auth\Register\Requests\RegisterRequest;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register/Register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $registerRequest): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $registerRequest->name,
            'email' => $registerRequest->email,
            'password' => Hash::make($registerRequest->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('home', absolute: false));
    }
}
