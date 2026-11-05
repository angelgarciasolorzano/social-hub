<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Providers\AppServiceProvider;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Spatie\Permission\Models\Permission;

function adminAuthorizationService(): AdminAuthorizationService
{
    return resolve(AdminAuthorizationService::class);
}

/**
 * The role of the `admin` guard with the given catalog name.
 */
function authorizationServiceRole(AdminRole $adminRole): Role
{
    return Role::query()
        ->where('name', $adminRole->value)
        ->where('guard_name', Role::GUARD_NAME)
        ->sole();
}

/**
 * A custom role of the `admin` guard with the given cataloged permissions.
 */
function authorizationServiceCustomRole(AdminPermission ...$adminPermissions): Role
{
    $role = RoleFactory::new()->createOne();
    $role->givePermissionTo(array_map(
        static fn (AdminPermission $adminPermission): string => $adminPermission->value,
        $adminPermissions,
    ));

    return $role;
}

function adminAuthorizationActivityCount(ActivityLogAdminEvent $activityLogAdminEvent): int
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->count();
}

/**
 * Run the callback and return the failure of the `AdminAuthorizationException` it throws.
 */
function adminAuthorizationFailureOf(Closure $callback): ?AdminAuthorizationFailure
{
    try {
        $callback();
    } catch (AdminAuthorizationException $adminAuthorizationException) {
        return $adminAuthorizationException->failure;
    }

    return null;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('grants a role once and records only the real change', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();

    $firstGrant = adminAuthorizationService()->grantRole($user, authorizationServiceRole(AdminRole::AdminUsersReader), $actor);
    $repeatedGrant = adminAuthorizationService()->grantRole($user, authorizationServiceRole(AdminRole::AdminUsersReader), $actor);

    $activityLog = ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleGranted->value)->sole();

    expect($firstGrant)->toBeTrue()
        ->and($repeatedGrant)->toBeFalse()
        ->and($user->fresh()?->hasRole(AdminRole::AdminUsersReader->value, 'admin'))->toBeTrue()
        ->and($activityLog->subject_id)->toBe($user->id)
        ->and($activityLog->causer_id)->toBe($actor->id)
        ->and($activityLog->properties?->get('role'))->toBe(AdminRole::AdminUsersReader->value);
});

it('revokes a role and records it, and records nothing when the user did not have it', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    $revocation = adminAuthorizationService()->revokeRole($user, authorizationServiceRole(AdminRole::AdminPostsReader), $actor);
    $repeatedRevocation = adminAuthorizationService()->revokeRole($user, authorizationServiceRole(AdminRole::AdminPostsReader), $actor);

    expect($revocation)->toBeTrue()
        ->and($repeatedRevocation)->toBeFalse()
        ->and($user->fresh()?->hasRole(AdminRole::AdminPostsReader->value, 'admin'))->toBeFalse()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleRevoked))->toBe(1);
});

it('refuses to revoke the role that gives the last administrator panel access', function (): void {
    $user = createAdminCommandActor();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokeRole($user, authorizationServiceRole(AdminRole::Admin), $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::LastAdminAccess)
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleRevoked))->toBe(0);
});

it('revokes the admin role when another administrator keeps panel access', function (): void {
    $actor = createAdminCommandActor();
    $user = createAdminCommandActor();

    expect(adminAuthorizationService()->revokeRole($user, authorizationServiceRole(AdminRole::Admin), $actor))->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeFalse();
});

it('grants and revokes role permissions with the role as the recorded subject', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $role = authorizationServiceRole(AdminRole::AdminUsersReader);

    $grant = adminAuthorizationService()->grantPermission($role, AdminPermission::AdminPostsViewAny, $actor);
    $userCanAfterGrant = $user->fresh()?->can(AdminPermission::AdminPostsViewAny->value);
    $repeatedGrant = adminAuthorizationService()->grantPermission($role, AdminPermission::AdminPostsViewAny, $actor);
    $revocation = adminAuthorizationService()->revokePermission($role, AdminPermission::AdminPostsViewAny, $actor);
    $repeatedRevocation = adminAuthorizationService()->revokePermission($role, AdminPermission::AdminPostsViewAny, $actor);

    $activityLog = ActivityLog::query()->where('event', ActivityLogAdminEvent::RolePermissionGranted->value)->sole();

    expect([$grant, $repeatedGrant, $revocation, $repeatedRevocation])->toBe([true, false, true, false])
        ->and($userCanAfterGrant)->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminPostsViewAny->value))->toBeFalse()
        ->and($activityLog->subject_type)->toBe(AppServiceProvider::ROLE_MORPH_NAME)
        ->and($activityLog->subject_id)->toBe($role->id)
        ->and($activityLog->causer_id)->toBe($actor->id)
        ->and($activityLog->properties?->get('role'))->toBe(AdminRole::AdminUsersReader->value)
        ->and($activityLog->properties?->get('permission'))->toBe(AdminPermission::AdminPostsViewAny->value)
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionRevoked))->toBe(1);
});

it('grants a custom role like a system role and records its stored name', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $customRole = authorizationServiceCustomRole(AdminPermission::AdminPostsViewAny);

    expect(adminAuthorizationService()->grantRole($user, $customRole, $actor))->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminPostsViewAny->value))->toBeTrue()
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleGranted->value)->sole()->properties?->get('role'))
        ->toBe($customRole->name);
});

it('refuses to change the permissions of the locked admin role and records nothing', function (): void {
    $user = createAdminCommandActor();
    $adminRole = authorizationServiceRole(AdminRole::Admin);

    $grantFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantPermission($adminRole, AdminPermission::AdminDashboardView, $user),
    );
    $revocationFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokePermission($adminRole, AdminPermission::AdminAccess, $user),
    );

    expect($grantFailure)->toBe(AdminAuthorizationFailure::RoleLocked)
        ->and($revocationFailure)->toBe(AdminAuthorizationFailure::RoleLocked)
        ->and($adminRole->refresh()->permissions->pluck('name')->all())->toBe([AdminPermission::AdminAccess->value])
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('refuses a permission whose requirements the role lacks', function (): void {
    $user = createAdminCommandActor();
    $role = authorizationServiceCustomRole();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantPermission($role, AdminPermission::AdminUsersView, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::PermissionDependencyMissing)
        ->and($role->permissions()->exists())->toBeFalse()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionGranted))->toBe(0);
});

it('refuses to revoke a permission that another permission of the role requires', function (): void {
    $user = createAdminCommandActor();
    $role = authorizationServiceRole(AdminRole::AdminUsersReader);

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokePermission($role, AdminPermission::AdminUsersViewAny, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::PermissionDependencyMissing)
        ->and($role->refresh()->permissions->pluck('name')->sort()->values()->all())->toBe([
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersViewAny->value,
        ])
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionRevoked))->toBe(0);
});

it('refuses to revoke admin.access from a role when nobody else would keep it', function (): void {
    $gatekeeperRole = authorizationServiceCustomRole(AdminPermission::AdminAccess);
    $user = createUser();
    $user->assignRole($gatekeeperRole);

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokePermission($gatekeeperRole, AdminPermission::AdminAccess, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::LastAdminAccess)
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionRevoked))->toBe(0);
});

it('revokes admin.access from a role when a user keeps it through another role', function (): void {
    $user = createAdminCommandActor();
    $gatekeeperRole = authorizationServiceCustomRole(AdminPermission::AdminAccess);
    $user->assignRole($gatekeeperRole);

    expect(adminAuthorizationService()->revokePermission($gatekeeperRole, AdminPermission::AdminAccess, $user))->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('reports a catalog that is not initialized and changes nothing', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $rolesReaderRole = authorizationServiceRole(AdminRole::AdminRolesReader);
    $usersReaderRole = authorizationServiceRole(AdminRole::AdminUsersReader);

    $rolesReaderRole->delete();
    $missingRoleFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantRole($user, $rolesReaderRole, $actor),
    );

    authorizationServiceRole(AdminRole::Admin)->delete();
    $missingCatalogFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantRole($user, $usersReaderRole, $actor),
    );

    expect($missingRoleFailure)->toBe(AdminAuthorizationFailure::RoleNotInitialized)
        ->and($missingCatalogFailure)->toBe(AdminAuthorizationFailure::CatalogNotInitialized)
        ->and($user->fresh()?->roles()->exists())->toBeFalse()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleGranted))->toBe(0);
});

it('refuses a role of another guard', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $webRole = Role::query()->create(['name' => 'web-only-role', 'guard_name' => 'web']);

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantRole($user, $webRole, $actor),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::RoleNotInitialized)
        ->and($user->fresh()?->roles()->exists())->toBeFalse();
});

it('reports a cataloged permission that is not initialized', function (): void {
    $user = createAdminCommandActor();
    Permission::query()->where('name', AdminPermission::AdminRolesUpdate->value)->delete();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantPermission(authorizationServiceRole(AdminRole::AdminUsersReader), AdminPermission::AdminRolesUpdate, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::PermissionNotInitialized)
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionGranted))->toBe(0);
});

it('describes every failure in both languages', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);

    foreach (AdminAuthorizationFailure::cases() as $adminAuthorizationFailure) {
        expect($adminAuthorizationFailure->message())
            ->toBe(translatedTextIn($preferredLocale, 'admin/roles.failures.'.$adminAuthorizationFailure->value))
            ->not->toStartWith('admin/roles.');
    }
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('ignores roles of other guards with the same name', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    Role::query()->create(['name' => AdminRole::AdminUsersReader->value, 'guard_name' => 'web']);

    adminAuthorizationService()->grantRole($user, authorizationServiceRole(AdminRole::AdminUsersReader), $actor);

    $adminGuardRole = Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->sole();

    expect($user->fresh()?->roles()->get()->modelKeys())->toBe([$adminGuardRole->getKey()]);
});

it('keeps the callers user instance in sync with the stored roles', function (): void {
    $actor = createAdminCommandActor();
    $user = User::query()->with('roles')->findOrFail(createUser()->id);

    adminAuthorizationService()->grantRole($user, authorizationServiceRole(AdminRole::AdminUsersReader), $actor);

    expect($user->hasRole(AdminRole::AdminUsersReader->value, 'admin'))->toBeTrue();
});
