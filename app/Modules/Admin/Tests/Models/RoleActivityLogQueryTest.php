<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * Record an admin activity with the given subject and properties, as the service would.
 *
 * @param  array<string, mixed>  $properties
 */
function recordRoleActivity(ActivityLogAdminEvent $activityLogAdminEvent, ?Model $model, array $properties = []): ActivityLog
{
    $activityLogFactory = ActivityLogFactory::new()->forEvent($activityLogAdminEvent)->state(['properties' => $properties]);

    if ($model instanceof Model) {
        $activityLogFactory = $activityLogFactory->performedOn($model);
    }

    return $activityLogFactory->createOne();
}

/**
 * The ids of the activity the role's query returns.
 *
 * @return list<int>
 */
function roleActivityIds(Role $role): array
{
    /** @var list<int> $activityIds */
    $activityIds = $role->activityLogQuery()->orderBy('id')->pluck('id')->all();

    return $activityIds;
}

it('includes the activity whose subject is the role', function (): void {
    $role = RoleFactory::new()->createOne();

    $activityLog = recordRoleActivity(ActivityLogAdminEvent::RoleCreated, $role, ['role' => $role->name]);
    $permissionGranted = recordRoleActivity(ActivityLogAdminEvent::RolePermissionGranted, $role, ['role' => $role->name, 'permission' => 'admin.users.view']);

    expect(roleActivityIds($role))->toBe([$activityLog->id, $permissionGranted->id]);
});

it('includes the grants and revocations of the role to users', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = createUser();

    $activityLog = recordRoleActivity(ActivityLogAdminEvent::RoleGranted, $user, ['role' => $role->name]);
    $revoked = recordRoleActivity(ActivityLogAdminEvent::RoleRevoked, $user, ['role' => $role->name]);

    expect(roleActivityIds($role))->toBe([$activityLog->id, $revoked->id]);
});

it('includes the bootstrap that gave the role', function (): void {
    $role = RoleFactory::new()->createOne();

    $activityLog = recordRoleActivity(ActivityLogAdminEvent::AdminBootstrapped, createUser(), ['roles' => ['admin', $role->name]]);

    expect(roleActivityIds($role))->toBe([$activityLog->id]);
});

it('excludes the activity of other roles and other events of the same users', function (): void {
    $role = RoleFactory::new()->createOne();
    $otherRole = RoleFactory::new()->createOne();
    $user = createUser();

    recordRoleActivity(ActivityLogAdminEvent::RolePermissionGranted, $otherRole, ['role' => $otherRole->name, 'permission' => 'admin.users.view']);
    recordRoleActivity(ActivityLogAdminEvent::RoleGranted, $user, ['role' => $otherRole->name]);
    recordRoleActivity(ActivityLogAdminEvent::AdminBootstrapped, $user, ['roles' => [$otherRole->name]]);
    recordRoleActivity(ActivityLogAdminEvent::InvitationIssued, $user, ['role' => $role->name]);

    expect(roleActivityIds($role))
        ->toBeEmpty();
});

it('leaves out the grants of a deleted role whose name the role reuses', function (): void {
    $deletedRole = RoleFactory::new()->createOne(['name' => 'custom-soporte']);
    $user = createUser();

    recordRoleActivity(ActivityLogAdminEvent::RoleGranted, $user, ['role' => 'custom-soporte']);
    recordRoleActivity(ActivityLogAdminEvent::RoleDeleted, $deletedRole, ['role' => 'custom-soporte']);
    $deletedRole->delete();

    $role = RoleFactory::new()->createOne(['name' => 'custom-soporte']);
    $activityLog = recordRoleActivity(ActivityLogAdminEvent::RoleGranted, $user, ['role' => 'custom-soporte']);

    expect(roleActivityIds($role))->toBe([$activityLog->id]);
});

it('ignores activity without properties or with a list of roles that is not a list', function (): void {
    $role = RoleFactory::new()->createOne();
    $user = createUser();

    recordRoleActivity(ActivityLogAdminEvent::RoleGranted, $user);
    recordRoleActivity(ActivityLogAdminEvent::AdminBootstrapped, $user, ['roles' => 'admin']);

    expect(roleActivityIds($role))
        ->toBeEmpty();
});
