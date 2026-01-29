<?php

namespace App\Auth\Password\Controllers;

use App\Auth\Password\Requests\NewPasswordRequest;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class NewPasswordController extends Controller
{
    /**
     * Show the password reset page.
     * 
     * @param Request&object{email: string} $request
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        return Inertia::render('auth/password/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     * 
     * @param NewPasswordRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(NewPasswordRequest $request)
    {
        /** @var string $status */
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user) use ($request): void {
            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return to_route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}