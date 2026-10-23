<?php

declare(strict_types=1);

use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;

/**
 * The AccountStatusChange factory, typed because Larastan does not infer #[UseFactory].
 */
function accountStatusChangeFactory(): AccountStatusChangeFactory
{
    /** @var AccountStatusChangeFactory $factory */
    $factory = AccountStatusChange::factory();

    return $factory;
}

it('creates a suspension with its account, reason and administrator', function (): void {
    $accountStatusChange = accountStatusChangeFactory()->createOne()->fresh();

    expect($accountStatusChange?->status)->toBe(AccountStatus::Suspended)
        ->and($accountStatusChange?->reason)->not->toBeEmpty()
        ->and($accountStatusChange?->user)->toBeInstanceOf(User::class)
        ->and($accountStatusChange?->changedBy)->toBeInstanceOf(User::class);
});

it('casts a reactivation to the active status', function (): void {
    $accountStatusChange = accountStatusChangeFactory()->reactivated()->createOne()->fresh();

    expect($accountStatusChange?->status)->toBe(AccountStatus::Active);
});

it('records the given administrator as the author of the change', function (): void {
    $user = createUser();

    $accountStatusChange = accountStatusChangeFactory()->changedBy($user)->createOne();

    expect($accountStatusChange->changedBy?->is($user))->toBeTrue();
});

it('returns no administrator once the author account is deleted', function (): void {
    $user = createUser();
    $accountStatusChange = accountStatusChangeFactory()->changedBy($user)->createOne();

    $user->delete();

    expect($accountStatusChange->fresh()?->changedBy)->toBeNull();
});

it('stores only the creation time', function (): void {
    $accountStatusChange = accountStatusChangeFactory()->createOne();

    expect($accountStatusChange->created_at)->not->toBeNull()
        ->and($accountStatusChange->getAttributes())->not->toHaveKey('updated_at');
});
