<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\RoleActivity;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The date of each user's latest grant of the role, as the users table reads it.
 *
 * @param  list<User>  $users
 * @return array<int, string|null>
 */
function latestGrantDates(Role $role, array $users): array
{
    $grantDates = [];

    $rows = User::query()
        ->whereKey(array_map(static fn (User $user): int => $user->id, $users))
        ->addSelect(['role_assigned_at' => $role->latestGrantDateSubquery()])
        ->get();

    foreach ($rows as $row) {
        $grantDate = $row->getAttribute('role_assigned_at');
        $grantDates[$row->id] = $grantDate === null ? null : CarbonImmutable::parse(\is_string($grantDate) ? $grantDate : '')->toDateTimeString();
    }

    return $grantDates;
}

it('returns the latest grant of each user with the administrator who made it', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = createUser();
    $administrator = createUser();

    RoleActivity::grant($role, $user, at: '2026-09-01 10:00:00');
    $activityLog = RoleActivity::grant($role, $user, $administrator, '2026-10-01 10:00:00');

    $latestGrants = $role->latestGrantsFor([$user->id]);

    expect(array_keys($latestGrants))->toBe([$user->id])
        ->and($latestGrants[$user->id]->id)->toBe($activityLog->id)
        ->and($latestGrants[$user->id]->relationLoaded('causer'))->toBeTrue()
        ->and($latestGrants[$user->id]->causer?->getKey())->toBe($administrator->id);
});

it('leaves out users without a recorded grant and grants of other roles', function (): void {
    $role = RoleFactory::new()->createOne();
    $userWithOtherRole = createUser();
    $userWithoutGrant = createUser();

    RoleActivity::grant(RoleFactory::new()->createOne(), $userWithOtherRole, at: '2026-10-01 10:00:00');

    expect($role->latestGrantsFor([$userWithOtherRole->id, $userWithoutGrant->id]))
        ->toBeEmpty()
        ->and($role->latestGrantsFor([]))
        ->toBeEmpty();
});

it('ignores the grants of a deleted role whose name the role reuses', function (): void {
    $deletedRole = RoleFactory::new()->createOne(['name' => 'custom-soporte']);
    $user = createUser();

    RoleActivity::grant($deletedRole, $user, at: '2026-09-01 10:00:00');
    RoleActivity::record(ActivityLogAdminEvent::RoleDeleted, $deletedRole, ['role' => 'custom-soporte']);
    $deletedRole->delete();

    $role = RoleFactory::new()->createOne(['name' => 'custom-soporte']);

    expect($role->latestGrantsFor([$user->id]))
        ->toBeEmpty()
        ->and(latestGrantDates($role, [$user]))->toBe([$user->id => null]);
});

it('loads the latest grants of many users in one query', function (): void {
    $role = RoleFactory::new()->createOne();
    $users = [createUser(), createUser(), createUser()];

    foreach ($users as $user) {
        RoleActivity::grant($role, $user, at: '2026-10-01 10:00:00');
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $latestGrants = $role->latestGrantsFor(array_map(static fn (User $user): int => $user->id, $users));

    $queryCount = \count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($latestGrants)->toHaveCount(3)
        ->and($queryCount)->toBe(2);
});

it('gives each user the date of the latest grant, or null without one', function (): void {
    $role = RoleFactory::new()->createOne();
    $userGrantedTwice = createUser();
    $userWithoutGrant = createUser();

    RoleActivity::grant($role, $userGrantedTwice, at: '2026-09-01 10:00:00');
    RoleActivity::grant($role, $userGrantedTwice, at: '2026-10-03 08:30:00');
    RoleActivity::grant(RoleFactory::new()->createOne(), $userWithoutGrant, at: '2026-10-05 10:00:00');

    expect(latestGrantDates($role, [$userGrantedTwice, $userWithoutGrant]))->toBe([
        $userGrantedTwice->id => '2026-10-03 08:30:00',
        $userWithoutGrant->id => null,
    ]);
});
