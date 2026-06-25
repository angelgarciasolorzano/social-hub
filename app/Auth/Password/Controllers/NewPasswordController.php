<?php

declare(strict_types=1);

namespace App\Auth\Password\Controllers;

use App\Auth\Password\Requests\NewPasswordRequest;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Show the password reset page.
     *
     * @param  Request&object{email: string}  $request
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/password/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @return RedirectResponse
     */
    public function store(NewPasswordRequest $newPasswordRequest)
    {
        /** @var string $status */
        $status = Password::reset($newPasswordRequest->only('email', 'password', 'password_confirmation', 'token'), function (User $user) use ($newPasswordRequest): void {
            $user->forceFill([
                'password' => Hash::make($newPasswordRequest->password),
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
