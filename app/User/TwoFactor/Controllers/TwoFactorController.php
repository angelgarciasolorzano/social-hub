<?php

declare(strict_types=1);

namespace App\User\TwoFactor\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use App\User\TwoFactor\Requests\TwoFactorDisableRequest;
use App\User\TwoFactor\Requests\TwoFactorRegenerateRecoveryCodesRequest;
use App\User\TwoFactor\Requests\TwoFactorRequest;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
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
    use InfersDeviceMetadata;

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

            $props['twoFactorConfirmedAt'] = $user->two_factor_confirmed_at?->toIso8601String();
            $props['recoveryCodesRegeneratedAt'] = $user->recovery_codes_regenerated_at?->toIso8601String();

            $props['trustedDevicesCount'] = $user->trustedDevices()->count();

            $props['trustedDevices'] = Inertia::optional(
                fn (): array => $user->trustedDevices()
                    ->latest('last_used_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve(request()))
                    ->all()
            );

            $props['trustedDevicesForRevoke'] = Inertia::optional(
                fn (): array => $user->trustedDevices()
                    ->latest('last_used_at')
                    ->get()
                    ->map(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve(request()))
                    ->all()
            );

            $props['firstTrustedDevice'] = Inertia::optional(
                function () use ($user): ?array {
                    $device = $user->trustedDevices()->oldest('created_at')->first();

                    if (! $device instanceof TrustedDevice) {
                        return null;
                    }

                    return new TrustedDeviceResource($device)->resolve(request());
                }
            );

            /** @var DeviceDetector $deviceDetector */
            $deviceDetector = resolve(DeviceDetector::class);

            $props['currentDevicePreview'] = Inertia::optional(
                fn (): array => $this->previewDevice(request(), $deviceDetector)
            );

            $props['currentDeviceMatch'] = Inertia::optional(
                function () use ($deviceDetector): ?array {
                    $device = $this->findCurrentDeviceMatch(request(), $deviceDetector);

                    if (! $device instanceof TrustedDevice) {
                        return null;
                    }

                    return new TrustedDeviceResource($device)->resolve(request());
                }
            );
        }

        return Inertia::render('setting/modules/twoFactor/TwoFactor', $props);
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

    /**
     * Build a preview of the device that would be created from the current request.
     *
     * @return array{browser: string, osName: string, userAgent: string|null, lastUsedAt: string, expiresAt: string}
     */
    private function previewDevice(Request $request, DeviceDetector $deviceDetector): array
    {
        $osInfo = $this->inferOsInfo($deviceDetector);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return [
            'browser' => $this->inferBrowser($deviceDetector),
            'osName' => $osInfo['name'],
            'userAgent' => $request->userAgent(),
            'isMobile' => $this->inferIsMobile($deviceDetector),
            'lastUsedAt' => CarbonImmutable::now()->toIso8601String(),
            'expiresAt' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes)->toIso8601String(),
        ];
    }

    /**
     * Find the still-active trusted device matching the current request, or null
     * when the device is unknown. Delegates to the model so the matching rules
     * (including the IP-required-for-match invariant) live in one place.
     */
    private function findCurrentDeviceMatch(Request $request, DeviceDetector $deviceDetector): ?TrustedDevice
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $userAgent = $request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        $osInfo = $this->inferOsInfo($deviceDetector);

        return TrustedDevice::findActiveMatch($user, $userAgent, $osInfo['name'], $request->ip());
    }
}
