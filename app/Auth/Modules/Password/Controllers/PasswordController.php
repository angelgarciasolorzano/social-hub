<?php

declare(strict_types=1);

namespace App\Auth\Modules\Password\Controllers;

use App\Auth\Modules\Password\Requests\PasswordRequest;
use App\Auth\Modules\Password\Services\PasswordTrustedDeviceRevoker;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PasswordController extends Controller
{
    public function update(
        PasswordRequest $passwordRequest,
        PasswordTrustedDeviceRevoker $passwordTrustedDeviceRevoker,
    ): RedirectResponse {
        $user = $passwordRequest->user();

        /** @var string|null $password */
        $password = $passwordRequest->input('password');

        abort_if($user === null || $password === null, 401);

        DB::transaction(function () use ($password, $passwordRequest, $passwordTrustedDeviceRevoker, $user): void {
            $user->update([
                'password' => Hash::make($password),
            ]);

            $passwordTrustedDeviceRevoker->revokeAll($user, $passwordRequest);
        });

        return Inertia::flash('success', 'Contraseña actualizada correctamente')->back();
    }
}
