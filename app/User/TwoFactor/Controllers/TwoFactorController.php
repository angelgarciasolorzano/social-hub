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

            $props['currentDevicePreview'] = Inertia::optional(
                fn (): array => $this->previewDevice(request())
            );

            $props['currentDeviceMatch'] = Inertia::optional(
                function (): ?array {
                    $device = $this->findCurrentDeviceMatch(request());

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
    private function previewDevice(Request $request): array
    {
        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return [
            'browser' => $this->inferBrowser($deviceDetector),
            'osName' => $osInfo['name'],
            'userAgent' => $request->userAgent(),
            'lastUsedAt' => CarbonImmutable::now()->toIso8601String(),
            'expiresAt' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes)->toIso8601String(),
        ];
    }

    /**
     * Find the active trusted device that fingerprints as the current request
     * (user_agent + OS name + IP, with a not-yet-expired `expires_at`). Returns
     * null when the request is made from an unknown device.
     */
    private function findCurrentDeviceMatch(Request $request): ?TrustedDevice
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $userAgent = $request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        $builder = $user->trustedDevices()
            ->where('user_agent', $userAgent)
            ->where('os_name', $osInfo['name'])
            ->where('expires_at', '>', CarbonImmutable::now());

        $ip = $request->ip();

        if ($ip !== null) {
            $builder->where('ip', $ip);
        }

        return $builder->first();
    }
}
