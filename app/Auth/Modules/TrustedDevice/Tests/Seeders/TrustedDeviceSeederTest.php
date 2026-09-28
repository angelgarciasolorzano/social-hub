<?php

declare(strict_types=1);

use App\Auth\Database\Seeders\TrustedDeviceSeeder;
use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Carbon\CarbonImmutable;

it('seeds at least ten trusted devices with lifecycle states and events for every user', function (): void {
    $users = [createUser(), createUser()];

    $this->seed(TrustedDeviceSeeder::class);

    $now = CarbonImmutable::now();

    foreach ($users as $user) {
        $totalDevices = TrustedDevice::withTrashed()
            ->where('user_id', $user->id)
            ->count();

        $deviceStateCounts = [
            'active' => $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->count(),
            'expiringSoon' => $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<', $now->addDays(7))
                ->count(),
            'inactive' => $user->trustedDevices()
                ->where('expires_at', '<=', $now)
                ->count(),
            'revoked' => TrustedDevice::onlyTrashed()
                ->where('user_id', $user->id)
                ->count(),
        ];

        $eventCounts = [
            'created' => $user->trustedDeviceEvents()
                ->where('action', TrustedDeviceAction::Created)
                ->count(),
            'renewed' => $user->trustedDeviceEvents()
                ->where('action', TrustedDeviceAction::Renewed)
                ->count(),
            'renamed' => $user->trustedDeviceEvents()
                ->where('action', TrustedDeviceAction::Renamed)
                ->count(),
            'revoked' => $user->trustedDeviceEvents()
                ->where('action', TrustedDeviceAction::Revoked)
                ->count(),
            'reactivated' => $user->trustedDeviceEvents()
                ->where('action', TrustedDeviceAction::Reactivated)
                ->count(),
        ];

        $createdEventsWithDeviceMetadata = $user->trustedDeviceEvents()
            ->where('action', TrustedDeviceAction::Created)
            ->whereNotNull('device_os_name')
            ->count();

        $seededDataHasExpectedCoverage = [
            'hasAtLeastTenDevices' => $totalDevices >= 10,
            'hasActiveDevices' => $deviceStateCounts['active'] > 0,
            'hasExpiringSoonDevices' => $deviceStateCounts['expiringSoon'] > 0,
            'hasInactiveDevices' => $deviceStateCounts['inactive'] > 0,
            'hasRevokedDevices' => $deviceStateCounts['revoked'] > 0,
            'hasCreationEventForEveryDevice' => $eventCounts['created'] === $totalDevices,
            'creationEventsHaveDeviceMetadata' => $createdEventsWithDeviceMetadata === $totalDevices,
            'hasRenewedEvents' => $eventCounts['renewed'] > 0,
            'hasRenamedEvents' => $eventCounts['renamed'] > 0,
            'hasRevokedEvents' => $eventCounts['revoked'] > 0,
            'hasReactivatedEvents' => $eventCounts['reactivated'] > 0,
        ];

        expect($seededDataHasExpectedCoverage)->toBe([
            'hasAtLeastTenDevices' => true,
            'hasActiveDevices' => true,
            'hasExpiringSoonDevices' => true,
            'hasInactiveDevices' => true,
            'hasRevokedDevices' => true,
            'hasCreationEventForEveryDevice' => true,
            'creationEventsHaveDeviceMetadata' => true,
            'hasRenewedEvents' => true,
            'hasRenamedEvents' => true,
            'hasRevokedEvents' => true,
            'hasReactivatedEvents' => true,
        ]);
    }
});
