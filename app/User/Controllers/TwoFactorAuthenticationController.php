<?php

declare(strict_types=1);

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use App\User\Requests\TwoFactorAuthentication\DisableTwoFactorRequest;
use App\User\Requests\TwoFactorAuthentication\RegenerateRecoveryCodesRequest;
use App\User\Requests\TwoFactorAuthentication\TwoFactorAuthenticationRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TwoFactorAuthenticationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? [new Middleware('password.confirm', only: ['index'])]
                : [];
    }

    private function getAuthenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function index(TwoFactorAuthenticationRequest $twoFactorAuthenticationRequest): Response
    {
        $user = $this->getAuthenticatedUser();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $twoFactorAuthenticationRequest->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('setting/modules/twoFactor/TwoFactorAuthentication', $props);
    }

    public function storeRecoveryCodes(
        RegenerateRecoveryCodesRequest $regenerateRecoveryCodesRequest,
        GenerateNewRecoveryCodes $generateNewRecoveryCodes
    ): RedirectResponse {
        $user = $regenerateRecoveryCodesRequest->user();

        abort_unless((bool) $user, 401);

        $generateNewRecoveryCodes($user);

        return Inertia::flash('success', 'Códigos de recuperación regenerados exitosamente.')->back();
    }

    public function destroy(
        DisableTwoFactorRequest $disableTwoFactorRequest,
        DisableTwoFactorAuthentication $disableTwoFactorAuthentication
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        $disableTwoFactorAuthentication($user);

        return Inertia::flash('success', 'La autenticación de dos factores ha sido desactivada correctamente.')
            ->back();
    }
}
