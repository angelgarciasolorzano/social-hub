<?php

declare(strict_types=1);

use App\Auth\Database\Seeders\TrustedDeviceSeeder;
use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Create users with a confirmed two-factor setup.
 *
 * @return Collection<int, User>
 */
function createUsersWithConfirmedTwoFactor(int $count): Collection
{
    return collect(range(1, $count))->map(createUserWithTwoFactor(...));
}

/**
 * Create users without two-factor authentication.
 *
 * @return Collection<int, User>
 */
function createUsersWithoutTwoFactor(int $count): Collection
{
    return collect(range(1, $count))->map(createUser(...));
}

function devicesOf(User $user): int
{
    return TrustedDevice::withTrashed()->where('user_id', $user->id)->count();
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

it('does not repeat a device scenario for the same user', function (): void {
    $user = createUserWithTwoFactor();

    $this->seed(TrustedDeviceSeeder::class);

    $deviceNames = TrustedDevice::withTrashed()->where('user_id', $user->id)->pluck('name');

    expect($deviceNames->unique())->toHaveCount($deviceNames->count());
});

it('records a Created event with the device metadata for every seeded device', function (): void {
    createUsersWithConfirmedTwoFactor(8);

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::Created)->count())
        ->toBe(TrustedDevice::withTrashed()->count())
        ->and(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::Created)->whereNull('device_os_name')->count())
        ->toBe(0);
});

it('records a Revoked event for every revoked device of a confirmed user', function (): void {
    createUsersWithConfirmedTwoFactor(8);

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDevice::onlyTrashed()->count())
        ->toBe(TrustedDeviceEvent::query()->where('action', TrustedDeviceAction::Revoked)->count());
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
