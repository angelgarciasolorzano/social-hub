<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An administrator who may manage roles and role permissions from the panel.
 */
function createRolesManager(): User
{
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminRolesManager->value);

    return $user;
}

/**
 * The role of the `admin` guard with the given catalog name.
 */
function syncedRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', Role::GUARD_NAME)
        ->sole();
}

/**
 * The cataloged permission names of a role, sorted.
 *
 * @return array<int, mixed>
 */
function catalogRolePermissionNames(AdminRole $adminRole): array
{
    return Role::findByName($adminRole->value, 'admin')
        ->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

/**
 * Run the sync and return the failure it throws, or null.
 */
function syncFailureOf(Closure $sync): ?AdminAuthorizationFailure
{
    try {
        $sync();
    } catch (AdminAuthorizationException $adminAuthorizationException) {
        return $adminAuthorizationException->failure;
    }

    return null;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('syncs the roles of a user, granting before revoking, and records each change', function (): void {
    $actor = createRolesManager();
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value, AdminRole::AdminActivityReader->value);

    $adminAuthorizationChanges = resolve(AdminAuthorizationService::class)->syncUserRoles(
        $user,
        [syncedRole(AdminRole::AdminPostsReader), syncedRole(AdminRole::AdminUsersReader)],
        $actor,
    );

    expect($adminAuthorizationChanges->granted)->toBe([AdminRole::AdminPostsReader->value])
        ->and($adminAuthorizationChanges->revoked)->toBe([AdminRole::AdminActivityReader->value])
        ->and($adminAuthorizationChanges->changeCount())->toBe(2)
        ->and($user->fresh()?->getRoleNames()->sort()->values()->all())->toBe([
            AdminRole::AdminPostsReader->value,
            AdminRole::AdminUsersReader->value,
        ])
        ->and(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(2);
});

it('returns no changes and records nothing when the roles are already the selected ones', function (): void {
    $actor = createRolesManager();
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $adminAuthorizationChanges = resolve(AdminAuthorizationService::class)->syncUserRoles($user, [syncedRole(AdminRole::AdminUsersReader)], $actor);

    expect($adminAuthorizationChanges->hasChanges())->toBeFalse()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('grants and revokes custom roles like system roles', function (): void {
    $actor = createRolesManager();
    $user = createUser();
    $auditorRole = RoleFactory::new()->createOne(['name' => 'custom-auditor']);
    $editorRole = RoleFactory::new()->createOne(['name' => 'custom-editor']);
    $user->assignRole($auditorRole);

    $adminAuthorizationChanges = resolve(AdminAuthorizationService::class)->syncUserRoles(
        $user,
        [$editorRole, syncedRole(AdminRole::AdminUsersReader)],
        $actor,
    );

    expect($adminAuthorizationChanges->granted)->toBe([AdminRole::AdminUsersReader->value, 'custom-editor'])
        ->and($adminAuthorizationChanges->revoked)->toBe(['custom-auditor'])
        ->and($user->fresh()?->getRoleNames()->sort()->values()->all())->toBe([
            AdminRole::AdminUsersReader->value,
            'custom-editor',
        ]);
});

it('keeps the roles of a user that belong to another guard', function (): void {
    $actor = createRolesManager();
    $user = createUser();
    $webRole = Role::query()->create(['name' => 'web-only-role', 'guard_name' => 'web']);
    $user->roles()->attach($webRole);
    $user->assignRole(AdminRole::AdminUsersReader->value);

    resolve(AdminAuthorizationService::class)->syncUserRoles($user, [], $actor);

    expect($user->fresh()?->roles->pluck('name')->all())->toBe(['web-only-role']);
});

it('syncs the permissions of a role', function (): void {
    $user = createRolesManager();

    $adminAuthorizationChanges = resolve(AdminAuthorizationService::class)->syncRolePermissions(
        syncedRole(AdminRole::AdminPostsReader),
        [AdminPermission::AdminPostsViewAny, AdminPermission::AdminActivityViewAny],
        $user,
    );

    expect($adminAuthorizationChanges->granted)->toBe([AdminPermission::AdminActivityViewAny->value])
        ->and($adminAuthorizationChanges->revoked)->toBe([AdminPermission::AdminPostsView->value])
        ->and(catalogRolePermissionNames(AdminRole::AdminPostsReader))->toBe([
            AdminPermission::AdminActivityViewAny->value,
            AdminPermission::AdminPostsViewAny->value,
        ]);
});

it('rolls the whole sync back when the manager would lose the permission to edit roles', function (): void {
    $user = createRolesManager();
    $permissionsBefore = catalogRolePermissionNames(AdminRole::AdminRolesManager);

    $failure = syncFailureOf(static fn (): mixed => resolve(AdminAuthorizationService::class)->syncRolePermissions(
        syncedRole(AdminRole::AdminRolesManager),
        [
            AdminPermission::AdminRolesViewAny,
            AdminPermission::AdminRolesView,
            AdminPermission::AdminUsersViewAny,
            AdminPermission::AdminUsersView,
            AdminPermission::AdminUsersRoles,
        ],
        $user,
    ));

    expect($failure)->toBe(AdminAuthorizationFailure::ActorWouldLoseAccess)
        ->and(catalogRolePermissionNames(AdminRole::AdminRolesManager))->toBe($permissionsBefore)
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse()
        ->and($user->fresh()?->can(AdminPermission::AdminRolesUpdate->value))->toBeTrue();
});

it('rolls the whole sync back when the manager would lose panel access', function (): void {
    $user = createRolesManager();
    createAdminCommandActor();

    $failure = syncFailureOf(static fn (): mixed => resolve(AdminAuthorizationService::class)->syncUserRoles(
        $user,
        [syncedRole(AdminRole::AdminRolesManager), syncedRole(AdminRole::AdminPostsReader)],
        $user,
    ));

    expect($failure)->toBe(AdminAuthorizationFailure::ActorWouldLoseAccess)
        ->and($user->fresh()?->getRoleNames()->sort()->values()->all())->toBe([
            AdminRole::Admin->value,
            AdminRole::AdminRolesManager->value,
        ])
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('rolls back the grants of a sync when a later revocation protects the last panel access', function (): void {
    $gatekeeperRole = RoleFactory::new()->createOne();
    $gatekeeperRole->givePermissionTo([
        AdminPermission::AdminAccess->value,
        AdminPermission::AdminRolesViewAny->value,
        AdminPermission::AdminRolesView->value,
        AdminPermission::AdminRolesUpdate->value,
    ]);
    $user = createUser();
    $user->assignRole($gatekeeperRole);

    $failure = syncFailureOf(static fn (): mixed => resolve(AdminAuthorizationService::class)->syncRolePermissions(
        $gatekeeperRole,
        [
            AdminPermission::AdminRolesViewAny,
            AdminPermission::AdminRolesView,
            AdminPermission::AdminRolesUpdate,
            AdminPermission::AdminPostsViewAny,
        ],
        $user,
    ));

    expect($failure)->toBe(AdminAuthorizationFailure::LastAdminAccess)
        ->and($gatekeeperRole->refresh()->permissions->pluck('name')->all())->toContain(AdminPermission::AdminAccess->value)
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse()
        ->and($user->fresh()?->can(AdminPermission::AdminPostsViewAny->value))->toBeFalse();
});

it('refuses to sync the permissions of the locked admin role', function (): void {
    $user = createRolesManager();

    $failure = syncFailureOf(static fn (): mixed => resolve(AdminAuthorizationService::class)->syncRolePermissions(
        syncedRole(AdminRole::Admin),
        [AdminPermission::AdminAccess, AdminPermission::AdminDashboardView],
        $user,
    ));

    expect($failure)->toBe(AdminAuthorizationFailure::RoleLocked)
        ->and(catalogRolePermissionNames(AdminRole::Admin))->toBe([AdminPermission::AdminAccess->value])
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('refuses a selection that lacks a requirement and writes nothing', function (): void {
    $user = createRolesManager();
    $permissionsBefore = catalogRolePermissionNames(AdminRole::AdminUsersReader);

    $failure = syncFailureOf(static fn (): mixed => resolve(AdminAuthorizationService::class)->syncRolePermissions(
        syncedRole(AdminRole::AdminUsersReader),
        [AdminPermission::AdminUsersView, AdminPermission::AdminPostsViewAny],
        $user,
    ));

    expect($failure)->toBe(AdminAuthorizationFailure::PermissionDependencyMissing)
        ->and(catalogRolePermissionNames(AdminRole::AdminUsersReader))->toBe($permissionsBefore)
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('locks the admin role before the user, the same order as the console commands', function (): void {
    $actor = createRolesManager();
    $user = createUser();

    DB::flushQueryLog();
    DB::enableQueryLog();

    resolve(AdminAuthorizationService::class)->syncUserRoles($user, [syncedRole(AdminRole::AdminPostsReader)], $actor);

    $lockedTables = [];

    foreach (DB::getQueryLog() as $loggedQuery) {
        $sql = mb_strtolower($loggedQuery['query']);

        if (str_contains($sql, 'for update') && preg_match('/from [`"]?(\w+)[`"]?/', $sql, $matches) === 1) {
            $lockedTables[] = $matches[1];
        }
    }

    DB::disableQueryLog();

    expect($lockedTables[0] ?? null)->toBe('roles')
        ->and($lockedTables)->toContain('users');
});
