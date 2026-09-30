<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('purges only revoked devices older than configured retention', function (): void {
    $user = createUser();
    $now = CarbonImmutable::parse('2026-09-30 12:00:00');
    $configuredDays = 30;

    config()->set('module.auth.trusted_devices.purge_after_days', $configuredDays);

    $this->travelTo($now->subDays($configuredDays + 1));
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $this->travelTo($now->subDays($configuredDays));
    $boundaryDevice = createTrustedDevice($user);
    $boundaryDevice->delete();

    $this->travelTo($now->subDays(10));
    $recentlyRevokedDevice = createTrustedDevice($user);
    $recentlyRevokedDevice->delete();

    $this->travelTo($now);
    $activeDevice = createTrustedDevice($user);

    $exitCode = Artisan::call('trusted-devices:purge');
    $commandOutput = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)
        ->toContain('Purged 1 trusted devices soft-deleted before')
        ->and(TrustedDevice::withTrashed()->whereKey($trustedDevice->getKey())
            ->exists())
        ->toBeFalse()
        ->and(TrustedDevice::withTrashed()->whereKey($boundaryDevice->getKey())
            ->exists())
        ->toBeTrue()
        ->and(TrustedDevice::withTrashed()->whereKey($recentlyRevokedDevice->getKey())
            ->exists())
        ->toBeTrue()
        ->and(TrustedDevice::query()->whereKey($activeDevice->getKey())
            ->exists())
        ->toBeTrue();
});

it('uses the days option instead of configured retention', function (): void {
    $user = createUser();
    $now = CarbonImmutable::parse('2026-09-30 12:00:00');

    config()->set('module.auth.trusted_devices.purge_after_days', 90);

    $this->travelTo($now->subDays(6));
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $this->travelTo($now->subDays(4));
    $recentlyRevokedDevice = createTrustedDevice($user);
    $recentlyRevokedDevice->delete();

    $this->travelTo($now);

    $exitCode = Artisan::call('trusted-devices:purge', ['--days' => '5']);
    $commandOutput = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)
        ->toContain('Purged 1 trusted devices soft-deleted before')
        ->and(TrustedDevice::withTrashed()->whereKey($trustedDevice->getKey())
            ->exists())
        ->toBeFalse()
        ->and(TrustedDevice::withTrashed()->whereKey($recentlyRevokedDevice->getKey())
            ->exists())
        ->toBeTrue();
});

it('rejects invalid retention days without deleting revoked devices', function (string $days): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $exitCode = Artisan::call('trusted-devices:purge', ['--days' => $days]);
    $commandOutput = Artisan::output();

    expect($exitCode)->toBe(Command::INVALID)
        ->and($commandOutput)
        ->toContain("purge_after_days must be a positive integer, got {$days}.")
        ->and(TrustedDevice::withTrashed()->whereKey($trustedDevice->getKey())->first()?->trashed())
        ->toBeTrue();
})->with([
    'zero days' => '0',
    'negative days' => '-1',
]);
