<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use App\User\Modules\AccountStatus\Services\AccountStatusService;

const ACCOUNT_STATUS_REASON = 'centinela-motivo-de-suspension';

/**
 * Resolve the service from the container, as the admin panel does.
 */
function accountStatusService(): AccountStatusService
{
    return resolve(AccountStatusService::class);
}

/**
 * Activity log rows recorded for the given admin event.
 *
 * @return array<int, ActivityLog>
 */
function accountStatusActivities(ActivityLogAdminEvent $activityLogAdminEvent): array
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->get()->all();
}

it('suspends an active account and records who did it and why', function (): void {
    $user = createUser();
    $administrator = createUser();

    $accountStatusChange = accountStatusService()->suspend($user, $administrator, '  '.ACCOUNT_STATUS_REASON.'  ');

    expect($user->isSuspended())->toBeTrue()
        ->and($accountStatusChange->status)->toBe(AccountStatus::Suspended)
        ->and($accountStatusChange->reason)->toBe(ACCOUNT_STATUS_REASON)
        ->and($accountStatusChange->user_id)->toBe($user->id)
        ->and($accountStatusChange->changed_by)->toBe($administrator->id);
});

it('revokes the trusted devices of a suspended account', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    createTrustedDevice($user);

    accountStatusService()->suspend($user, createUser(), ACCOUNT_STATUS_REASON);

    expect($user->trustedDevices()->count())->toBe(0)
        ->and(TrustedDevice::onlyTrashed()->whereBelongsTo($user)->count())->toBe(2);
});

it('audits a suspension without the reason', function (): void {
    $user = createUser();
    $administrator = createUser();

    $accountStatusChange = accountStatusService()->suspend($user, $administrator, ACCOUNT_STATUS_REASON);
    $activities = accountStatusActivities(ActivityLogAdminEvent::AccountSuspended);

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($accountStatusChange))->toBeTrue()
        ->and($activities[0]->causer?->is($administrator))->toBeTrue()
        ->and($activities[0]->properties?->get('target_user_id'))->toBe($user->id)
        ->and(ActivityLog::query()->get()->toJson())->not->toContain(ACCOUNT_STATUS_REASON);
});

it('reactivates a suspended account keeping its content and its suspension record', function (): void {
    $user = createSuspendedUser();
    $post = createPost($user);
    $administrator = createUser();

    $accountStatusChange = accountStatusService()->reactivate($user, $administrator, ACCOUNT_STATUS_REASON);

    expect($user->isSuspended())->toBeFalse()
        ->and($accountStatusChange->status)->toBe(AccountStatus::Active)
        ->and($post->fresh())->not->toBeNull()
        ->and(accountStatusActivities(ActivityLogAdminEvent::AccountReactivated))->toHaveCount(1);
});

it('keeps the history of a suspension after the account is reactivated', function (): void {
    $user = createUser();
    $administrator = createUser();

    accountStatusService()->suspend($user, $administrator, ACCOUNT_STATUS_REASON);
    accountStatusService()->reactivate($user, $administrator, ACCOUNT_STATUS_REASON);

    expect($user->accountStatusChanges()->pluck('status')->all())
        ->toBe([AccountStatus::Active, AccountStatus::Suspended]);
});

it('does not restore the revoked trusted devices on reactivation', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    $administrator = createUser();

    accountStatusService()->suspend($user, $administrator, ACCOUNT_STATUS_REASON);
    accountStatusService()->reactivate($user, $administrator, ACCOUNT_STATUS_REASON);

    expect($user->trustedDevices()->count())->toBe(0);
});

it('rejects a blank or too long reason without changing anything', function (string $reason): void {
    $user = createUser();
    createTrustedDevice($user);

    expect(fn (): AccountStatusChange => accountStatusService()->suspend($user, createUser(), $reason))
        ->toThrow(InvalidArgumentException::class)
        ->and($user->fresh()?->isSuspended())->toBeFalse()
        ->and(AccountStatusChange::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(0)
        ->and($user->trustedDevices()->count())->toBe(1);
})->with([
    'blank' => '   ',
    'too long' => str_repeat('a', AccountStatusChange::REASON_MAX_LENGTH + 1),
]);

it('rejects suspending an account that is already suspended without writing anything', function (): void {
    $user = createSuspendedUser();

    expect(fn (): AccountStatusChange => accountStatusService()->suspend($user, createUser(), ACCOUNT_STATUS_REASON))
        ->toThrow(LogicException::class)
        ->and(AccountStatusChange::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(0);
});

it('rejects reactivating an account that is already active without writing anything', function (): void {
    $user = createUser();

    expect(fn (): AccountStatusChange => accountStatusService()->reactivate($user, createUser(), ACCOUNT_STATUS_REASON))
        ->toThrow(LogicException::class)
        ->and(AccountStatusChange::query()->count())->toBe(0);
});

it('checks the current status in the database rather than the stale model', function (): void {
    $user = createUser();
    $administrator = createUser();
    $staleUser = User::query()->findOrFail($user->id);

    accountStatusService()->suspend($user, $administrator, ACCOUNT_STATUS_REASON);

    expect(fn (): AccountStatusChange => accountStatusService()->suspend($staleUser, $administrator, ACCOUNT_STATUS_REASON))
        ->toThrow(LogicException::class)
        ->and(AccountStatusChange::query()->count())->toBe(1);
});
