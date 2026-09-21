<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyAllRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyForceRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceReactivateRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceUpdateRequest;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrustedDeviceController extends Controller
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

    public function __construct(private readonly TrustedDeviceService $trustedDeviceService) {}

    /**
     * Hard-delete any active sibling sharing the same fingerprint before a
     * reactivate, locked against concurrent reads and recorded as Revoked.
     */
    private function dropDuplicateActiveDevice(
        ?User $user,
        TrustedDevice $trustedDevice,
        TrustedDeviceReactivateRequest $trustedDeviceReactivateRequest,
    ): void {
        if (! $user instanceof User) {
            return;
        }

        $duplicate = TrustedDevice::findActiveMatch(
            $user,
            $trustedDevice->user_agent,
            $trustedDevice->os_name,
            $trustedDevice->ip,
            lockForUpdate: true,
        );

        if ($duplicate instanceof TrustedDevice && $duplicate->id !== $trustedDevice->id) {
            TrustedDeviceEvent::record(
                trustedDevice: $duplicate,
                user: $user,
                trustedDeviceAction: TrustedDeviceAction::Revoked,
                request: $trustedDeviceReactivateRequest,
            );

            $duplicate->forceDelete();
        }
    }

    public function update(TrustedDeviceUpdateRequest $trustedDeviceUpdateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceUpdateRequest->user()?->getKey(), 403);

        DB::transaction(function () use ($trustedDeviceUpdateRequest, $trustedDevice): void {
            $trustedDevice->forceFill([
                'name' => $trustedDeviceUpdateRequest->string('name')->toString(),
            ])->save();

            TrustedDeviceEvent::record(
                trustedDevice: $trustedDevice,
                user: $trustedDeviceUpdateRequest->user(),
                trustedDeviceAction: TrustedDeviceAction::Renamed,
                request: $trustedDeviceUpdateRequest,
            );
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo renombrado correctamente.',
        ])->back();
    }

    public function renew(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $request->user()?->getKey(), 403);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        DB::transaction(function () use ($request, $trustedDevice, $cookieLifetimeMinutes): void {
            $trustedDevice->forceFill([
                'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
            ])->save();

            TrustedDeviceEvent::record(
                trustedDevice: $trustedDevice,
                user: $request->user(),
                trustedDeviceAction: TrustedDeviceAction::Renewed,
                request: $request,
            );
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Confianza renovada correctamente.',
        ])->back();
    }

    public function store(TrustedDeviceStoreRequest $trustedDeviceStoreRequest): RedirectResponse
    {
        $user = $trustedDeviceStoreRequest->user();

        abort_unless($user instanceof User, 401);

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        $userAgent = $trustedDeviceStoreRequest->userAgent();
        $ip = $trustedDeviceStoreRequest->ip();

        if ($userAgent !== null && $ip !== null) {
            $existingMatch = TrustedDevice::findAnyMatchForFingerprint(
                $user,
                $userAgent,
                $osInfo['name'],
                $ip,
            );

            if ($existingMatch instanceof TrustedDevice) {
                if ($existingMatch->deleted_at !== null) {
                    return Inertia::flash([
                        'type' => 'error',
                        'message' => 'Este dispositivo ya está registrado pero fue revocado. Reactívalo desde la lista de dispositivos revocados en lugar de agregarlo nuevamente.',
                    ])->back();
                }

                return Inertia::flash([
                    'type' => 'error',
                    'message' => 'Este dispositivo ya está registrado como de confianza.',
                ])->back();
            }
        }

        $token = $this->mintToken();
        $deviceName = $trustedDeviceStoreRequest->string('name')->toString();

        DB::transaction(function () use (
            $user,
            $deviceDetector,
            $osInfo,
            $token,
            $deviceName,
            $userAgent,
            $ip,
            $trustedDeviceStoreRequest,
        ): TrustedDevice {
            if ($userAgent !== null && $ip !== null) {
                $existingMatch = TrustedDevice::findActiveMatch(
                    $user,
                    $userAgent,
                    $osInfo['name'],
                    $ip,
                    lockForUpdate: true,
                );

                if ($existingMatch instanceof TrustedDevice) {
                    return $existingMatch;
                }
            }

            $trustedDevice = $this->trustedDeviceService->create(
                user: $user,
                deviceDetector: $deviceDetector,
                tokenHash: $token['hash'],
                name: $deviceName,
                userAgent: $userAgent,
                ip: $ip,
            );

            if ($userAgent !== null) {
                TrustedDevice::pruneOlder($user, $userAgent, $osInfo['name'], $ip, $trustedDevice->id);
            }

            if ($trustedDevice->wasRecentlyCreated) {
                TrustedDeviceEvent::record(
                    trustedDevice: $trustedDevice,
                    user: $user,
                    trustedDeviceAction: TrustedDeviceAction::Created,
                    request: $trustedDeviceStoreRequest,
                );
            }

            return $trustedDevice;
        });

        $this->queueTrustedDeviceCookie($token['token']);

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo agregado correctamente.',
        ])->back();
    }

    public function destroy(TrustedDeviceDestroyRequest $trustedDeviceDestroyRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceDestroyRequest->user()?->getKey(), 403);

        DB::transaction(function () use ($trustedDeviceDestroyRequest, $trustedDevice): void {
            TrustedDeviceEvent::record(
                trustedDevice: $trustedDevice,
                user: $trustedDeviceDestroyRequest->user(),
                trustedDeviceAction: TrustedDeviceAction::Revoked,
                request: $trustedDeviceDestroyRequest,
            );

            $trustedDevice->delete();
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo de confianza revocado correctamente.',
        ])->back();
    }

    public function destroyAll(TrustedDeviceDestroyAllRequest $trustedDeviceDestroyAllRequest): RedirectResponse
    {
        $user = $trustedDeviceDestroyAllRequest->user();

        abort_unless($user instanceof User, 401);

        DB::transaction(function () use ($trustedDeviceDestroyAllRequest, $user): void {
            $devices = $user->trustedDevices()->latest('last_used_at')->get();

            foreach ($devices as $device) {
                TrustedDeviceEvent::record(
                    trustedDevice: $device,
                    user: $user,
                    trustedDeviceAction: TrustedDeviceAction::RevokedAll,
                    request: $trustedDeviceDestroyAllRequest,
                );
            }

            $user->trustedDevices()->delete();
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Todos los dispositivos de confianza fueron revocados.',
        ])->back();
    }

    public function reactivate(TrustedDeviceReactivateRequest $trustedDeviceReactivateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        $user = $trustedDeviceReactivateRequest->user();

        abort_unless($user instanceof User, 401);
        abort_unless($trustedDevice->user_id === $user->getKey(), 403);
        abort_if($trustedDevice->deleted_at === null, 404);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        DB::transaction(function () use ($trustedDeviceReactivateRequest, $trustedDevice, $user, $cookieLifetimeMinutes): void {
            $this->dropDuplicateActiveDevice($user, $trustedDevice, $trustedDeviceReactivateRequest);

            $newToken = $this->mintToken();

            $trustedDevice->forceFill([
                'token_hash' => $newToken['hash'],
                'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
                'last_used_at' => CarbonImmutable::now(),
            ])->save();

            $trustedDevice->restore();

            TrustedDeviceEvent::record(
                trustedDevice: $trustedDevice,
                user: $user,
                trustedDeviceAction: TrustedDeviceAction::Reactivated,
                request: $trustedDeviceReactivateRequest,
            );

            $this->queueTrustedDeviceCookie($newToken['token']);
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo reactivado correctamente. Se regeneró el token de confianza por seguridad.',
        ])->back();
    }

    public function forceDestroy(TrustedDeviceDestroyForceRequest $trustedDeviceDestroyForceRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceDestroyForceRequest->user()?->getKey(), 403);
        abort_if($trustedDevice->deleted_at === null, 404);

        DB::transaction(function () use ($trustedDeviceDestroyForceRequest, $trustedDevice): void {
            TrustedDeviceEvent::record(
                trustedDevice: $trustedDevice,
                user: $trustedDeviceDestroyForceRequest->user(),
                trustedDeviceAction: TrustedDeviceAction::Revoked,
                request: $trustedDeviceDestroyForceRequest,
            );

            $trustedDevice->forceDelete();
        });

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo eliminado permanentemente.',
        ])->back();
    }
}
