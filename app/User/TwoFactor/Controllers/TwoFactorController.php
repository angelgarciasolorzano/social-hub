<?php

declare(strict_types=1);

namespace App\User\TwoFactor\Controllers;

use App\Http\Controllers\Controller;
use App\User\Models\User;
use App\User\TwoFactor\Requests\TwoFactorDisableRequest;
use App\User\TwoFactor\Requests\TwoFactorRegenerateRecoveryCodesRequest;
use App\User\TwoFactor\Requests\TwoFactorRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TwoFactorController extends Controller implements HasMiddleware
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

    public function index(TwoFactorRequest $twoFactorRequest): Response
    {
        $user = $this->getAuthenticatedUser();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $twoFactorRequest->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('setting/modules/twoFactor/TwoFactorAuthentication', $props);
    }

    public function storeRecoveryCodes(
        TwoFactorRegenerateRecoveryCodesRequest $twoFactorRegenerateRecoveryCodesRequest,
        GenerateNewRecoveryCodes $generateNewRecoveryCodes
    ): RedirectResponse {
        $user = $twoFactorRegenerateRecoveryCodesRequest->user();

        abort_unless((bool) $user, 401);

        $generateNewRecoveryCodes($user);

        return Inertia::flash('success', 'Códigos de recuperación regenerados exitosamente.')->back();
    }

    public function destroy(
        TwoFactorDisableRequest $twoFactorDisableRequest,
        DisableTwoFactorAuthentication $disableTwoFactorAuthentication
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        $disableTwoFactorAuthentication($user);

        return Inertia::flash('success', 'La autenticación de dos factores ha sido desactivada correctamente.')
            ->back();
    }
}
