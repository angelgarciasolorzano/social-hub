<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Seeders\AccountStatusSeeder;
use App\User\Database\Seeders\UserSeeder;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Tests\TestCase;

const SEEDED_REGULAR_USER_COUNT = 20;

/**
 * Create an administrator, the local test user and regular users, half of them with an active trusted device.
 *
 * @return array{administrator: User, testUser: User}
 */
function seedAccountStatusFixtures(TestCase $testCase): array
{
    $testCase->seed(AdminAuthorizationSeeder::class);

    $administrator = createUser();
    $administrator->assignRole(AdminRole::Admin->value);

    $testUser = createUser();
    $testUser->forceFill(['email' => UserSeeder::TEST_USER_EMAIL])->save();

    foreach (range(1, SEEDED_REGULAR_USER_COUNT) as $index) {
        $user = createUser();

        if ($index % 2 === 0) {
            createTrustedDevice($user, ['last_used_at' => now()->subDays(3), 'expires_at' => now()->addDays(20)]);
        }
    }

    return ['administrator' => $administrator, 'testUser' => $testUser];
}

function runAccountStatusSeeder(int $suspendedSharePercent = 25, int $reactivatedSharePercent = 25): void
{
    new AccountStatusSeeder($suspendedSharePercent, $reactivatedSharePercent)->run();
}

it('suspends the configured share of regular accounts with a reason and an administrator', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    ['administrator' => $administrator] = seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder();

    $suspendedUsers = User::query()->where('status', AccountStatus::Suspended)->get();

    expect($suspendedUsers)->toHaveCount(intdiv(SEEDED_REGULAR_USER_COUNT * 25, 100));

    foreach ($suspendedUsers as $suspendedUser) {
        $latestChange = $suspendedUser->accountStatusChanges()->firstOrFail();

        expect($latestChange->status)->toBe(AccountStatus::Suspended)
            ->and($latestChange->reason)->not->toBeEmpty()
            ->and($latestChange->changed_by)->toBe($administrator->id)
            ->and($latestChange->created_at?->isPast())->toBeTrue();
    }
});

it('gives reactivated accounts a suspension followed by a later reactivation', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder(suspendedSharePercent: 0);

    $reactivatedUsers = User::query()->whereHas('accountStatusChanges')->get();

    expect($reactivatedUsers)->toHaveCount(intdiv(SEEDED_REGULAR_USER_COUNT * 25, 100));

    foreach ($reactivatedUsers as $reactivatedUser) {
        expect($reactivatedUser->status)->toBe(AccountStatus::Active)
            ->and($reactivatedUser->accountStatusChanges()->pluck('status')->all())
            ->toBe([AccountStatus::Active, AccountStatus::Suspended]);
    }
});

it('never changes the status of administrators or the test user', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    ['administrator' => $administrator, 'testUser' => $testUser] = seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder(100, 0);

    expect($administrator->refresh()->status)->toBe(AccountStatus::Active)
        ->and($testUser->refresh()->status)->toBe(AccountStatus::Active)
        ->and($administrator->accountStatusChanges()->exists())->toBeFalse()
        ->and($testUser->accountStatusChanges()->exists())->toBeFalse();
});

it('leaves suspended accounts without active trusted devices and records their revocation', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder(100, 0);

    $suspendedUserIds = User::query()->where('status', AccountStatus::Suspended)->pluck('id');
    $revokedDevices = TrustedDevice::onlyTrashed()->whereIn('user_id', $suspendedUserIds)->get();

    expect(TrustedDevice::query()->whereIn('user_id', $suspendedUserIds)->exists())->toBeFalse()
        ->and($revokedDevices)->toHaveCount(SEEDED_REGULAR_USER_COUNT / 2);

    foreach ($revokedDevices as $revokedDevice) {
        expect($revokedDevice->user->trustedDeviceEvents()->where('action', TrustedDeviceAction::RevokedAll)->exists())->toBeTrue();
    }
});

it('writes no activity log rows so ActivityLogSeeder still runs afterwards', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder();

    expect(ActivityLog::query()->exists())->toBeFalse();
});

it('does not duplicate the history when it runs twice', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    seedAccountStatusFixtures($testCase);

    runAccountStatusSeeder();
    $statusChangeCount = AccountStatusChange::query()->count();

    runAccountStatusSeeder();

    expect(AccountStatusChange::query()->count())->toBe($statusChangeCount)
        ->and($statusChangeCount)->toBeGreaterThan(0);
});

it('does nothing when there is no administrator to attribute the changes to', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
    createUser();

    runAccountStatusSeeder(100, 0);

    expect(AccountStatusChange::query()->exists())->toBeFalse();
});
