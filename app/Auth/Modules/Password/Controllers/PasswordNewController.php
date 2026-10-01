<?php

declare(strict_types=1);

namespace App\Auth\Modules\Password\Controllers;

use App\Auth\Modules\Password\Requests\PasswordNewRequest;
use App\Auth\Modules\Password\Services\PasswordTrustedDeviceRevoker;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordNewController extends Controller
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
     */
    public function store(
        PasswordNewRequest $passwordNewRequest,
        PasswordTrustedDeviceRevoker $passwordTrustedDeviceRevoker,
    ): RedirectResponse {
        /** @var string $status */
        $status = Password::reset(
            $passwordNewRequest->only(
                'email', 'password', 'password_confirmation', 'token'
            ), function (User $user) use ($passwordNewRequest, $passwordTrustedDeviceRevoker): void {
                DB::transaction(function () use ($passwordNewRequest, $passwordTrustedDeviceRevoker, $user): void {
                    $user->forceFill([
                        'password' => Hash::make($passwordNewRequest->password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    $passwordTrustedDeviceRevoker->revokeAll($user, $passwordNewRequest);
                });

                event(new PasswordReset($user));
            });

        if ($status === Password::PASSWORD_RESET) {
            return to_route('login')->with('status', __($status, [], 'es'));
        }

        throw ValidationException::withMessages([
            'email' => [__($status, [], 'es')],
        ]);
    }
}
