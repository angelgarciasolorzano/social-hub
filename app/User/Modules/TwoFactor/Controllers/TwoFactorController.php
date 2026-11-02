<?php

declare(strict_types=1);

namespace App\User\Modules\TwoFactor\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Props\TrustedDeviceCurrentProps;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\Http\Controllers\Controller;
use App\Support\Translation\FrontendTranslations;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use App\User\Modules\TwoFactor\Enums\TwoFactorStatus;
use App\User\Modules\TwoFactor\Requests\TwoFactorDisableRequest;
use App\User\Modules\TwoFactor\Requests\TwoFactorRegenerateRecoveryCodesRequest;
use App\User\Modules\TwoFactor\Requests\TwoFactorRequest;
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

    /**
     * The trusted device groups are registered too: the page shows the trusted devices panel and its
     * add and revoke-all dialogs.
     */
    public function index(
        TwoFactorRequest $twoFactorRequest,
        TrustedDeviceCurrentProps $trustedDeviceCurrentProps,
        FrontendTranslations $frontendTranslations,
    ): Response {
        $user = $this->getAuthenticatedUser();

        $frontendTranslations->use(
            'setting/common',
            'setting/two-factor',
            'setting/trusted-devices',
            'setting/trusted-device-dialogs',
        );

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $twoFactorRequest->ensureStateIsValid();

            $twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();

            $props['twoFactorEnabled'] = $twoFactorEnabled;
            $props['twoFactorStatus'] = TwoFactorStatus::fromUser($user)->value;
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

            $props['twoFactorConfirmedAt'] = $user->two_factor_confirmed_at?->toIso8601String();
            $props['recoveryCodesRegeneratedAt'] = $user->recovery_codes_regenerated_at?->toIso8601String();

            $props['trustedDevicesCount'] = $twoFactorEnabled
                ? $user->trustedDevices()->count()
                : 0;

            $props['trustedDevices'] = Inertia::optional(
                fn (): array => $user->trustedDevices()
                    ->withTrashed()
                    ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                    ->latest('last_used_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve(request()))
                    ->all()
            );

        }

        return Inertia::render('setting/modules/twoFactor/TwoFactor', [
            ...$props,
            $trustedDeviceCurrentProps,
        ]);
    }

    public function storeRecoveryCodes(
        TwoFactorRegenerateRecoveryCodesRequest $twoFactorRegenerateRecoveryCodesRequest,
        GenerateNewRecoveryCodes $generateNewRecoveryCodes
    ): RedirectResponse {
        $user = $twoFactorRegenerateRecoveryCodesRequest->user();

        abort_unless((bool) $user, 401);

        $generateNewRecoveryCodes($user);

        return Inertia::flash('success', TranslatedText::of('setting/two-factor.messages.recovery_codes_regenerated'))->back();
    }

    public function destroy(
        TwoFactorDisableRequest $twoFactorDisableRequest,
        DisableTwoFactorAuthentication $disableTwoFactorAuthentication
    ): RedirectResponse {
        $user = $this->getAuthenticatedUser();

        $disableTwoFactorAuthentication($user);

        return Inertia::flash('success', TranslatedText::of('setting/two-factor.messages.disabled'))->back();
    }
}
