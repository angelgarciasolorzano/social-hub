<?php

declare(strict_types=1);

namespace App\Auth\Password\Controllers;

use App\Auth\Password\Requests\PasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('setting/modules/password/EditPassword');
    }

    public function update(PasswordRequest $passwordRequest): RedirectResponse
    {
        $user = $passwordRequest->user();

        /** @var string|null $password */
        $password = $passwordRequest->input('password');

        abort_if($user === null || $password === null, 401);

        $user->update([
            'password' => Hash::make($password),
        ]);

        return Inertia::flash('success', 'Contraseña actualizada correctamente')->back();
    }
}
