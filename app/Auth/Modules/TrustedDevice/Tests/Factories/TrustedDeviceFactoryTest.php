<?php

declare(strict_types=1);

use App\Auth\Database\Factories\TrustedDeviceFactory;
use App\Auth\Models\TrustedDevice;
use DeviceDetector\DeviceDetector;
use Illuminate\Support\Collection;

const DEVICE_FACTORY_SAMPLE_SIZE = 300;

/**
 * Names DeviceDetector gives to each browser the factory can generate.
 */
const DEVICE_DETECTOR_CLIENT_NAMES = [
    'Chrome' => ['Chrome', 'Chrome Mobile', 'Chrome Mobile iOS'],
    'Firefox' => ['Firefox', 'Firefox Mobile'],
    'Safari' => ['Safari', 'Mobile Safari'],
    'Edge' => ['Microsoft Edge'],
];

/**
 * Build many devices without saving them, so the sample is cheap to create.
 *
 * @return Collection<int, TrustedDevice>
 */
function makeDevices(?TrustedDeviceFactory $trustedDeviceFactory = null): Collection
{
    $trustedDeviceFactory ??= TrustedDeviceFactory::new();

    return Collection::times(DEVICE_FACTORY_SAMPLE_SIZE, fn (): TrustedDevice => $trustedDeviceFactory->makeOne(['user_id' => 1]));
}

it('generates a user agent that DeviceDetector reads as the same system, browser, version and device type', function (): void {
    $operatingSystemFamilies = ['Windows' => 'Windows', 'Mac' => 'Mac', 'Linux' => 'GNU/Linux', 'iOS' => 'iOS', 'Android' => 'Android'];

    foreach (makeDevices() as $trustedDevice) {
        $deviceDetector = new DeviceDetector($trustedDevice->user_agent);
        $deviceDetector->skipBotDetection();
        $deviceDetector->parse();

        $detectedVersion = $deviceDetector->getClient('version');

        expect($deviceDetector->getOs('family'))->toBe($operatingSystemFamilies[$trustedDevice->os_name])
            ->and(DEVICE_DETECTOR_CLIENT_NAMES[$trustedDevice->browser])->toContain($deviceDetector->getClient('name'))
            ->and(explode('.', is_string($detectedVersion) ? $detectedVersion : '')[0])->toBe($trustedDevice->browser_version)
            ->and($deviceDetector->isMobile() || $deviceDetector->isTablet())->toBe($trustedDevice->is_mobile);
    }
});

it('only generates iOS or Android for mobile devices and the other systems for desktop ones', function (): void {
    foreach (makeDevices() as $trustedDevice) {
        expect($trustedDevice->os_name)->toBeIn($trustedDevice->is_mobile ? ['iOS', 'Android'] : ['Windows', 'Mac', 'Linux']);
    }
});

it('generates Safari only on Mac and iOS and Edge only on Windows', function (): void {
    foreach (makeDevices() as $trustedDevice) {
        if ($trustedDevice->browser === 'Safari') {
            expect($trustedDevice->os_name)->toBeIn(['Mac', 'iOS']);
        }

        if ($trustedDevice->browser === 'Edge') {
            expect($trustedDevice->os_name)->toBe('Windows');
        }
    }
});

it('generates a mobile share close to 40 percent', function (): void {
    $mobileShare = makeDevices()->where('is_mobile', true)->count() / DEVICE_FACTORY_SAMPLE_SIZE;

    expect($mobileShare)->toBeBetween(0.30, 0.50);
});

it('generates every operating system', function (): void {
    expect(makeDevices()->pluck('os_name')->unique())->toHaveCount(5);
});

it('names each device after its operating system', function (): void {
    $nameKeywords = ['Windows' => ['Computadora', 'Windows'], 'Mac' => ['Mac'], 'Linux' => ['Linux'], 'iOS' => ['iPhone', 'iPad'], 'Android' => ['Android']];

    foreach (makeDevices() as $trustedDevice) {
        $matchesOperatingSystem = collect($nameKeywords[$trustedDevice->os_name])->contains(fn (string $keyword): bool => str_contains($trustedDevice->name, $keyword));

        expect($matchesOperatingSystem)->toBeTrue();
    }
});

it('trusts a default device for 30 days from now', function (): void {
    $trustedDevice = TrustedDeviceFactory::new()->makeOne(['user_id' => 1]);

    expect($trustedDevice->expires_at->isFuture())->toBeTrue()
        ->and($trustedDevice->expires_at->diffInDays(now(), absolute: true))->toBeGreaterThan(29);
});

it('makes an active device expire in the future but not within a week', function (): void {
    foreach (makeDevices(TrustedDeviceFactory::new()->active()) as $trustedDevice) {
        expect($trustedDevice->expires_at->isFuture())->toBeTrue()
            ->and($trustedDevice->expires_at->greaterThan(now()->addDays(7)))->toBeTrue();
    }
});

it('makes an expiring-soon device expire within a week', function (): void {
    foreach (makeDevices(TrustedDeviceFactory::new()->expiringSoon()) as $trustedDevice) {
        expect($trustedDevice->expires_at->isFuture())->toBeTrue()
            ->and($trustedDevice->expires_at->lessThan(now()->addDays(7)))->toBeTrue();
    }
});

it('makes an expired device expire in the past and last be used before that', function (): void {
    foreach (makeDevices(TrustedDeviceFactory::new()->expired()) as $trustedDevice) {
        expect($trustedDevice->expires_at->isPast())->toBeTrue()
            ->and($trustedDevice->last_used_at?->lessThanOrEqualTo($trustedDevice->expires_at))->toBeTrue();
    }
});

it('makes a revoked device soft deleted after it was created and last used before that', function (): void {
    foreach (makeDevices(TrustedDeviceFactory::new()->revoked()) as $trustedDevice) {
        $revokedAt = $trustedDevice->deleted_at;
        $lastUsedAt = $trustedDevice->last_used_at;

        expect($revokedAt)->not->toBeNull()
            ->and($lastUsedAt)->not->toBeNull();

        if ($revokedAt === null || $lastUsedAt === null) {
            continue;
        }

        expect($revokedAt->greaterThanOrEqualTo($trustedDevice->created_at))->toBeTrue()
            ->and($lastUsedAt->lessThanOrEqualTo($revokedAt))->toBeTrue();
    }
});

it('never uses a device before it was created', function (): void {
    $factories = [
        TrustedDeviceFactory::new()->active(),
        TrustedDeviceFactory::new()->expiringSoon(),
        TrustedDeviceFactory::new()->expired(),
        TrustedDeviceFactory::new()->revoked(),
    ];

    foreach ($factories as $factory) {
        foreach (makeDevices($factory) as $trustedDevice) {
            expect($trustedDevice->last_used_at?->greaterThanOrEqualTo($trustedDevice->created_at))->toBeTrue();
        }
    }
});

it('keeps an explicit attribute over the generated one', function (): void {
    $trustedDevice = TrustedDeviceFactory::new()->makeOne(['user_id' => 1, 'os_name' => 'Linux', 'is_mobile' => false]);

    expect($trustedDevice->os_name)->toBe('Linux')
        ->and($trustedDevice->is_mobile)->toBeFalse();
});
