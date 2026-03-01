<?php

namespace App\Auth\Password\Controllers;

use App\Auth\Password\Requests\PasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function __invoke(PasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        /** @var string $password */
        $password = $request->input('password');

        if (! $user || ! $password) {
            abort(401);
        }

        $user->update([
            'password' => Hash::make($password),
        ]);

        return back();
    }
}
