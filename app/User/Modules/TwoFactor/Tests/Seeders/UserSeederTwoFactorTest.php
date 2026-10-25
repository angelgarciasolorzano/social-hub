<?php

declare(strict_types=1);

use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

const SEEDED_TEST_USER_EMAIL = 'angel@gmail.com';

/**
 * Run the user seeder for a smaller number of users, so tests stay fast.
 * The public disk is faked because the seeder attaches seeding images when they exist.
 */
function seedUsers(int $userCount): void
{
    Storage::fake('public');

    new UserSeeder($userCount)->run();
}

/**
 * @return Collection<int, User>
 */
function usersWithConfirmedTwoFactor(): Collection
{
    return User::query()->whereNotNull('two_factor_secret')->whereNotNull('two_factor_confirmed_at')->get();
}

/**
 * @return Collection<int, User>
 */
function usersWithPendingTwoFactor(): Collection
{
    return User::query()->whereNotNull('two_factor_secret')->whereNull('two_factor_confirmed_at')->get();
}

it('gives a quarter of the seeded users confirmed two-factor authentication', function (): void {
    seedUsers(20);

    expect(usersWithConfirmedTwoFactor())->toHaveCount(5);
});

it('leaves a twentieth of the seeded users with a pending two-factor secret', function (): void {
    seedUsers(20);

    expect(usersWithPendingTwoFactor())->toHaveCount(1);
});

it('leaves the rest of the seeded users without two-factor authentication', function (): void {
    seedUsers(20);

    expect(User::query()->whereNull('two_factor_secret')->count())->toBe(15);
});

it('keeps the test user without two-factor authentication', function (): void {
    seedUsers(20);

    $testUser = User::query()->where('email', SEEDED_TEST_USER_EMAIL)->firstOrFail();

    expect($testUser->two_factor_secret)->toBeNull()
        ->and($testUser->two_factor_confirmed_at)->toBeNull();
});

it('does not create the two-factor users before all the others', function (): void {
    seedUsers(20);

    $firstUserIds = User::query()->orderBy('id')->limit(5)->pluck('id')->all();

    expect(usersWithConfirmedTwoFactor()->modelKeys())->not->toBe($firstUserIds);
});

it('rounds the shares down when there are only a few users', function (): void {
    seedUsers(3);

    expect(usersWithConfirmedTwoFactor())->toBeEmpty()
        ->and(usersWithPendingTwoFactor())->toBeEmpty();
});

it('seeds only the test user when there are no other users to create', function (): void {
    seedUsers(0);

    expect(User::query()->pluck('email')->all())->toBe([SEEDED_TEST_USER_EMAIL]);
});
