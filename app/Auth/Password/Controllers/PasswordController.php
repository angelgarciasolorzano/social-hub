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
        $request->user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return back();
    }
}
