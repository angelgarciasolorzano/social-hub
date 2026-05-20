<?php

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

    public function update(PasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        /** @var string $password */
        $password = $request->input('password');

        abort_if(! $user || ! $password, 401);

        $user->update([
            'password' => Hash::make($password),
        ]);

        return Inertia::flash('success', 'Contraseña actualizada correctamente')->back();
    }
}
