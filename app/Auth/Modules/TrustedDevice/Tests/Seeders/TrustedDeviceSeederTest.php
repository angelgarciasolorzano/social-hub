<?php

declare(strict_types=1);

use App\Auth\Database\Seeders\TrustedDeviceSeeder;
use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Illuminate\Support\Collection;
use Tests\TestCase;

const DEVICE_SEEDER_SAMPLE_USERS = 25;

/**
 * Create users with a confirmed two-factor setup.
 *
 * @return Collection<int, User>
 */
function createUsersWithConfirmedTwoFactor(int $count): Collection
{
    return Collection::times($count, createUserWithTwoFactor(...));
}

/**
 * Create users without two-factor authentication.
 *
 * @return Collection<int, User>
 */
function createUsersWithoutTwoFactor(int $count): Collection
{
    return Collection::times($count, createUser(...));
}

function devicesOf(User $user): int
{
    return TrustedDevice::withTrashed()->where('user_id', $user->id)->count();
}

/**
 * Seed a large enough sample of confirmed users for every device state to show up.
 *
 * @return Collection<int, TrustedDevice>
 */
function seedSampleOfConfirmedUserDevices(TestCase $testCase): Collection
{
    createUsersWithConfirmedTwoFactor(DEVICE_SEEDER_SAMPLE_USERS);

    $testCase->seed(TrustedDeviceSeeder::class);

    return TrustedDevice::withTrashed()->get();
}

/**
 * The events recorded for one device, oldest first. Events keep a snapshot of
 * the device (its IP and user agent), not a link to it.
 *
 * @return Collection<int, TrustedDeviceEvent>
 */
function eventsOfDevice(TrustedDevice $trustedDevice): Collection
{
    return TrustedDeviceEvent::query()
        ->where('user_id', $trustedDevice->user_id)
        ->where('ip', $trustedDevice->ip)
        ->where('user_agent', $trustedDevice->user_agent)
        ->orderBy('id')
        ->get();
}

/**
 * @param  Collection<int, TrustedDeviceEvent>  $events
 * @return Collection<int, int>
 */
function eventTimestamps(Collection $events): Collection
{
    return $events->map(fn (TrustedDeviceEvent $trustedDeviceEvent): int => $trustedDeviceEvent->created_at?->getTimestamp() ?? 0)->values();
}

it('creates no devices when there are no users', function (): void {
    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDevice::withTrashed()->count())->toBe(0);
});

it('gives users with confirmed two-factor authentication between 0 and 30 devices', function (): void {
    $users = createUsersWithConfirmedTwoFactor(8);

    $this->seed(TrustedDeviceSeeder::class);

    expect($users->map(devicesOf(...)))->each->toBeLessThanOrEqual(30)
        ->and($users->map(devicesOf(...))->sum())->toBeGreaterThan(0);
});

it('does not give every confirmed user the same number of devices', function (): void {
    $users = createUsersWithConfirmedTwoFactor(DEVICE_SEEDER_SAMPLE_USERS);

    $this->seed(TrustedDeviceSeeder::class);

    expect($users->map(devicesOf(...))->unique()->count())->toBeGreaterThan(3);
});

it('seeds active, expiring-soon, expired and revoked devices', function (): void {
    $trustedDevices = seedSampleOfConfirmedUserDevices($this);

    $active = $trustedDevices->filter(fn (TrustedDevice $trustedDevice): bool => $trustedDevice->deleted_at === null && $trustedDevice->expires_at->greaterThan(now()->addDays(7)));
    $expiringSoon = $trustedDevices->filter(fn (TrustedDevice $trustedDevice): bool => $trustedDevice->deleted_at === null && $trustedDevice->expires_at->isFuture() && $trustedDevice->expires_at->lessThan(now()->addDays(7)));
    $expired = $trustedDevices->filter(fn (TrustedDevice $trustedDevice): bool => $trustedDevice->deleted_at === null && $trustedDevice->expires_at->isPast());
    $revoked = $trustedDevices->filter(fn (TrustedDevice $trustedDevice): bool => $trustedDevice->deleted_at !== null);

    expect($active)->not->toBeEmpty()
        ->and($expiringSoon)->not->toBeEmpty()
        ->and($expired)->not->toBeEmpty()
        ->and($revoked)->not->toBeEmpty();
});

it('seeds both mobile and desktop devices on several operating systems', function (): void {
    $trustedDevices = seedSampleOfConfirmedUserDevices($this);

    expect($trustedDevices->pluck('is_mobile')->unique()->count())->toBe(2)
        ->and($trustedDevices->pluck('os_name')->unique()->count())->toBeGreaterThan(2);
});

it('records a Created event with the device metadata for every seeded device', function (): void {
    $trustedDevices = seedSampleOfConfirmedUserDevices($this);

    expect(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::Created)->count())->toBe($trustedDevices->count())
        ->and(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::Created)->whereNull('device_os_name')->count())->toBe(0);
});

it('dates the Created event of every device when the device was created', function (): void {
    foreach (seedSampleOfConfirmedUserDevices($this) as $trustedDevice) {
        expect(eventsOfDevice($trustedDevice)->first()?->created_at?->timestamp)->toBe($trustedDevice->created_at->timestamp);
    }
});

it('records the events of every device in chronological order starting with Created', function (): void {
    foreach (seedSampleOfConfirmedUserDevices($this) as $trustedDevice) {
        $events = eventsOfDevice($trustedDevice);

        expect($events->first()?->action)->toBe(TrustedDeviceAction::Created)
            ->and(eventTimestamps($events)->all())->toBe(eventTimestamps($events)->sort()->values()->all());
    }
});

it('records a Revoked event when each revoked device was revoked', function (): void {
    foreach (seedSampleOfConfirmedUserDevices($this)->filter(fn (TrustedDevice $trustedDevice): bool => $trustedDevice->deleted_at !== null) as $trustedDevice) {
        $revocation = eventsOfDevice($trustedDevice)->firstWhere('action', TrustedDeviceAction::Revoked);

        expect($revocation?->created_at?->timestamp)->toBe($trustedDevice->deleted_at?->timestamp);
    }
});

it('leaves a reactivated device active after its Created, Revoked and Reactivated events', function (): void {
    $reactivatedDevices = seedSampleOfConfirmedUserDevices($this)->filter(
        fn (TrustedDevice $trustedDevice): bool => eventsOfDevice($trustedDevice)->contains('action', TrustedDeviceAction::Reactivated)
    );

    expect($reactivatedDevices)->not->toBeEmpty();

    foreach ($reactivatedDevices as $reactivatedDevice) {
        expect($reactivatedDevice->deleted_at)->toBeNull()
            ->and($reactivatedDevice->expires_at->isFuture())->toBeTrue()
            ->and(eventsOfDevice($reactivatedDevice)->pluck('action')->all())->toBe([
                TrustedDeviceAction::Created,
                TrustedDeviceAction::Revoked,
                TrustedDeviceAction::Reactivated,
            ]);
    }
});

it('extends the trust of a renewed device for 30 days from the renewal', function (): void {
    $renewedDevices = seedSampleOfConfirmedUserDevices($this)->filter(
        fn (TrustedDevice $trustedDevice): bool => eventsOfDevice($trustedDevice)->contains('action', TrustedDeviceAction::Renewed)
    );

    expect($renewedDevices)->not->toBeEmpty();

    foreach ($renewedDevices as $renewedDevice) {
        $renewal = eventsOfDevice($renewedDevice)->firstWhere('action', TrustedDeviceAction::Renewed);

        expect($renewedDevice->expires_at->timestamp)->toBe($renewal?->created_at?->addDays(30)->timestamp);
    }
});

it('creates no devices for users with a pending two-factor secret', function (): void {
    $user = UserFactory::new()->twoFactorPending()->createOne();

    $this->seed(TrustedDeviceSeeder::class);

    expect(devicesOf($user))->toBe(0);
});

it('creates no active devices for users without two-factor authentication', function (): void {
    createUsersWithoutTwoFactor(20);

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDevice::query()->count())->toBe(0);
});

it('leaves a twentieth of the users without two-factor authentication with revoked devices', function (): void {
    $users = createUsersWithoutTwoFactor(20);

    $this->seed(TrustedDeviceSeeder::class);

    expect($users->filter(fn (User $user): bool => devicesOf($user) > 0))->toHaveCount(1);
});

it('only gives revoked devices to the users who disabled two-factor authentication', function (): void {
    createUsersWithoutTwoFactor(20);

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDevice::onlyTrashed()->count())->toBe(TrustedDevice::withTrashed()->count())
        ->and(TrustedDevice::withTrashed()->count())->toBeGreaterThan(0);
});

it('records a RevokedAll event for every device of the users who disabled two-factor authentication', function (): void {
    createUsersWithoutTwoFactor(20);

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::RevokedAll)->count())
        ->toBe(TrustedDevice::withTrashed()->count());
});

it('never gives devices to the test user, even when everyone else is picked', function (): void {
    $testUser = createUser();
    $testUser->forceFill(['email' => 'angel@gmail.com'])->save();
    $others = createUsersWithoutTwoFactor(3);

    new TrustedDeviceSeeder(100)->run();

    expect(devicesOf($testUser))->toBe(0)
        ->and($others->map(devicesOf(...))->every(fn (int $devices): bool => $devices > 0))->toBeTrue();
});
