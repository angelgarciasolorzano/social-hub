<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Record an activity of the role at the given time, as the service would.
 */
function recordRoleChange(Role $role, ActivityLogAdminEvent $activityLogAdminEvent, User $user, string $changedAt): ActivityLog
{
    return ActivityLogFactory::new()
        ->forEvent($activityLogAdminEvent)
        ->performedOn($role)
        ->causedBy($user)
        ->createOne(['properties' => ['role' => $role->name], 'created_at' => CarbonImmutable::parse($changedAt)]);
}

/**
 * Record that an administrator gave the role to a user at the given time.
 */
function recordRoleGrantToUser(Role $role, User $user, string $grantedAt): ActivityLog
{
    return ActivityLogFactory::new()
        ->forEvent(ActivityLogAdminEvent::RoleGranted)
        ->performedOn($user)
        ->causedBy(createUser())
        ->createOne(['properties' => ['role' => $role->name], 'created_at' => CarbonImmutable::parse($grantedAt)]);
}

/**
 * The date of each role's latest change, as the roles table reads it.
 *
 * @param  list<Role>  $roles
 * @return array<int|string, string|null>
 */
function lastChangeDates(array $roles): array
{
    $changeDates = [];

    $rows = Role::query()
        ->whereKey(array_map(static fn (Role $role): int|string => $role->id, $roles))
        ->addSelect(['roles.*', 'last_changed_at' => Role::lastChangeDateSubquery()])
        ->get();

    foreach ($rows as $row) {
        $changeDate = $row->getAttribute('last_changed_at');
        $changeDates[$row->id] = $changeDate === null ? null : CarbonImmutable::parse(\is_string($changeDate) ? $changeDate : '')->toDateTimeString();
    }

    return $changeDates;
}

it('returns the latest change of each role with the administrator who made it and ignores grants to users', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = createUser();

    recordRoleChange($role, ActivityLogAdminEvent::RoleCreated, createUser(), '2026-09-01 10:00:00');
    $activityLog = recordRoleChange($role, ActivityLogAdminEvent::RolePermissionGranted, $user, '2026-10-01 10:00:00');
    recordRoleGrantToUser($role, createUser(), '2026-10-15 10:00:00');

    $latestChanges = Role::latestChangesFor([$role->id]);

    expect(array_keys($latestChanges))->toBe([$role->id])
        ->and($latestChanges[$role->id]->id)->toBe($activityLog->id)
        ->and($latestChanges[$role->id]->relationLoaded('causer'))->toBeTrue()
        ->and($latestChanges[$role->id]->causer?->getKey())->toBe($user->id);
});

it('loads the latest changes of many roles in two queries and leaves out roles without activity', function (): void {
    $roles = [RoleFactory::new()->createOne(), RoleFactory::new()->createOne(), RoleFactory::new()->createOne()];
    $roleWithoutActivity = RoleFactory::new()->createOne();

    foreach ($roles as $role) {
        recordRoleChange($role, ActivityLogAdminEvent::RoleUpdated, createUser(), '2026-10-01 10:00:00');
    }

    DB::enableQueryLog();
    $latestChanges = Role::latestChangesFor([...array_map(static fn (Role $role): int|string => $role->id, $roles), $roleWithoutActivity->id]);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($latestChanges)->toHaveCount(3)
        ->and($latestChanges)->not->toHaveKey($roleWithoutActivity->id)
        ->and($queryCount)->toBe(2)
        ->and(Role::latestChangesFor([]))->toBeEmpty();
});

it('returns the creation of a custom role and nothing for a seeded system role', function (): void {
    $customRole = RoleFactory::new()->createOne();
    $user = createUser();
    $systemRole = RoleFactory::new()->system(AdminRole::AdminPostsReader)->createOne();

    $activityLog = recordRoleChange($customRole, ActivityLogAdminEvent::RoleCreated, $user, '2026-09-01 10:00:00');
    recordRoleChange($customRole, ActivityLogAdminEvent::RoleUpdated, createUser(), '2026-10-01 10:00:00');
    recordRoleChange($systemRole, ActivityLogAdminEvent::RolePermissionGranted, createUser(), '2026-10-01 10:00:00');

    $creations = Role::creationsFor([$customRole->id, $systemRole->id]);

    expect(array_keys($creations))->toBe([$customRole->id])
        ->and($creations[$customRole->id]->id)->toBe($activityLog->id)
        ->and($creations[$customRole->id]->causer?->getKey())->toBe($user->id)
        ->and(Role::creationsFor([]))->toBeEmpty();
});

it('counts the duplication of a copy as its creation, with the administrator who duplicated it', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = createUser();

    $activityLog = recordRoleChange($role, ActivityLogAdminEvent::RoleDuplicated, $user, '2026-09-01 10:00:00');
    recordRoleChange($role, ActivityLogAdminEvent::RolePermissionGranted, createUser(), '2026-10-01 10:00:00');

    $creations = Role::creationsFor([$role->id]);

    expect(array_keys($creations))->toBe([$role->id])
        ->and($creations[$role->id]->id)->toBe($activityLog->id)
        ->and($creations[$role->id]->causer?->getKey())->toBe($user->id);
});

it('selects the date of the latest change of each role, or null without one', function (): void {
    $changedRole = RoleFactory::new()->createOne();
    $grantedOnlyRole = RoleFactory::new()->createOne();

    recordRoleChange($changedRole, ActivityLogAdminEvent::RoleCreated, createUser(), '2026-09-01 10:00:00');
    recordRoleChange($changedRole, ActivityLogAdminEvent::RoleUpdated, createUser(), '2026-10-01 10:00:00');
    recordRoleGrantToUser($grantedOnlyRole, createUser(), '2026-10-15 10:00:00');

    expect(lastChangeDates([$changedRole, $grantedOnlyRole]))->toBe([
        $changedRole->id => '2026-10-01 10:00:00',
        $grantedOnlyRole->id => null,
    ]);
});
