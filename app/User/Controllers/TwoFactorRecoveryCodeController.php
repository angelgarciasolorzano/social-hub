<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Requests\RegenerateRecoveryCodesRequest;
use Inertia\Inertia;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TwoFactorRecoveryCodeController extends Controller
{
    public function store(
        RegenerateRecoveryCodesRequest $request,
        GenerateNewRecoveryCodes $generate
    ): RedirectResponse {
        $user = $request->user();

        abort_unless((bool) $user, 401);

        $generate($user);

        return Inertia::flash('success', 'Códigos de recuperación regenerados exitosamente.')->back();
    }
}
