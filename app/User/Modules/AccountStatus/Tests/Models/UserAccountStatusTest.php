<?php

declare(strict_types=1);

use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Database\Factories\UserFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * Create a persisted suspended User.
 */
function createSuspendedUser(): User
{
    /** @var UserFactory $factory */
    $factory = User::factory();

    return $factory->suspended()->createOne();
}

/**
 * Create a status change of the given account at the given time.
 */
function createAccountStatusChangeAt(User $user, string $createdAt): AccountStatusChange
{
    /** @var AccountStatusChangeFactory $factory */
    $factory = AccountStatusChange::factory();

    return $factory->for($user)->createOne(['created_at' => $createdAt]);
}

it('creates accounts as active', function (): void {
    $user = createUser();

    expect($user->fresh()?->status)->toBe(AccountStatus::Active)
        ->and($user->isSuspended())->toBeFalse();
});

it('reports a suspended account as suspended', function (): void {
    $user = createSuspendedUser();

    expect($user->fresh()?->status)->toBe(AccountStatus::Suspended)
        ->and($user->isSuspended())->toBeTrue();
});

it('does not let mass assignment change the status', function (): void {
    $user = createUser();

    expect(fn (): bool => $user->update(['status' => AccountStatus::Suspended->value]))
        ->toThrow(MassAssignmentException::class)
        ->and($user->fresh()?->status)->toBe(AccountStatus::Active);
});

it('lists only the account status changes of the account, most recent first', function (): void {
    $user = createUser();
    $accountStatusChange = createAccountStatusChangeAt($user, '2026-10-01 10:00:00');
    $newerChange = createAccountStatusChangeAt($user, '2026-10-20 10:00:00');
    createAccountStatusChangeAt(createUser(), '2026-10-21 10:00:00');

    expect($user->accountStatusChanges()->pluck('id')->all())->toBe([$newerChange->id, $accountStatusChange->id]);
});
