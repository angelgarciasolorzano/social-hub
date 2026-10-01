<?php

declare(strict_types=1);

namespace App\Auth\Modules\Password\Services;

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\Password\Requests\PasswordNewRequest;
use App\Auth\Modules\Password\Requests\PasswordRequest;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class PasswordTrustedDeviceRevoker
{
    public function __construct(
        private TrustedDeviceDashboardCache $trustedDeviceDashboardCache,
    ) {}

    public function revokeAll(User $user, PasswordRequest|PasswordNewRequest $passwordRequest): int
    {
        $revokedDevicesCount = DB::transaction(function () use ($passwordRequest, $user): int {
            $trustedDevices = $user->trustedDevices()
                ->latest('last_used_at')
                ->lockForUpdate()
                ->get();

            foreach ($trustedDevices as $trustedDevice) {
                TrustedDeviceEvent::record(
                    trustedDevice: $trustedDevice,
                    user: $user,
                    trustedDeviceAction: TrustedDeviceAction::RevokedAll,
                    request: $passwordRequest,
                );
            }

            $revokedDevicesCount = $trustedDevices->count();

            $user->trustedDevices()->delete();

            return $revokedDevicesCount;
        });

        if ($revokedDevicesCount > 0) {
            DB::afterCommit(function () use ($user): void {
                $this->trustedDeviceDashboardCache->invalidate($user);
            });
        }

        return $revokedDevicesCount;
    }
}
