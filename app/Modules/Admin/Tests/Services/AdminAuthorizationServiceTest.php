<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Exceptions\AdminAuthorizationException;
use App\Modules\Admin\Services\AdminAuthorizationService;
use App\Providers\AppServiceProvider;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function adminAuthorizationService(): AdminAuthorizationService
{
    return resolve(AdminAuthorizationService::class);
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

    $firstGrant = adminAuthorizationService()->grantRole($user, AdminRole::AdminUsersReader, $actor);
    $repeatedGrant = adminAuthorizationService()->grantRole($user, AdminRole::AdminUsersReader, $actor);

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

    $revocation = adminAuthorizationService()->revokeRole($user, AdminRole::AdminPostsReader, $actor);
    $repeatedRevocation = adminAuthorizationService()->revokeRole($user, AdminRole::AdminPostsReader, $actor);

    expect($revocation)->toBeTrue()
        ->and($repeatedRevocation)->toBeFalse()
        ->and($user->fresh()?->hasRole(AdminRole::AdminPostsReader->value, 'admin'))->toBeFalse()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleRevoked))->toBe(1);
});

it('refuses to revoke the role that gives the last administrator panel access', function (): void {
    $user = createAdminCommandActor();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokeRole($user, AdminRole::Admin, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::LastAdminAccess)
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleRevoked))->toBe(0);
});

it('revokes the admin role when another administrator keeps panel access', function (): void {
    $actor = createAdminCommandActor();
    $user = createAdminCommandActor();

    expect(adminAuthorizationService()->revokeRole($user, AdminRole::Admin, $actor))->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeFalse();
});

it('grants and revokes role permissions with the role as the recorded subject', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $grant = adminAuthorizationService()->grantPermission(AdminRole::AdminUsersReader, AdminPermission::AdminPostsView, $actor);
    $userCanAfterGrant = $user->fresh()?->can(AdminPermission::AdminPostsView->value);
    $repeatedGrant = adminAuthorizationService()->grantPermission(AdminRole::AdminUsersReader, AdminPermission::AdminPostsView, $actor);
    $revocation = adminAuthorizationService()->revokePermission(AdminRole::AdminUsersReader, AdminPermission::AdminPostsView, $actor);
    $repeatedRevocation = adminAuthorizationService()->revokePermission(AdminRole::AdminUsersReader, AdminPermission::AdminPostsView, $actor);

    $role = Role::query()->where('name', AdminRole::AdminUsersReader->value)->where('guard_name', 'admin')->sole();
    $activityLog = ActivityLog::query()->where('event', ActivityLogAdminEvent::RolePermissionGranted->value)->sole();

    expect([$grant, $repeatedGrant, $revocation, $repeatedRevocation])->toBe([true, false, true, false])
        ->and($userCanAfterGrant)->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminPostsView->value))->toBeFalse()
        ->and($activityLog->subject_type)->toBe(AppServiceProvider::ROLE_MORPH_NAME)
        ->and($activityLog->subject_id)->toBe($role->id)
        ->and($activityLog->causer_id)->toBe($actor->id)
        ->and($activityLog->properties?->get('role'))->toBe(AdminRole::AdminUsersReader->value)
        ->and($activityLog->properties?->get('permission'))->toBe(AdminPermission::AdminPostsView->value)
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionRevoked))->toBe(1);
});

it('refuses to revoke admin.access from the admin role when nobody else would keep it', function (): void {
    $user = createAdminCommandActor();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->revokePermission(AdminRole::Admin, AdminPermission::AdminAccess, $user),
    );

    expect($failure)->toBe(AdminAuthorizationFailure::LastAdminAccess)
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RolePermissionRevoked))->toBe(0);
});

it('revokes admin.access from the admin role when a user keeps it through another role', function (): void {
    $user = createAdminCommandActor();
    Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->sole()
        ->givePermissionTo(AdminPermission::AdminAccess->value);
    $user->assignRole(AdminRole::AdminUsersReader->value);

    expect(adminAuthorizationService()->revokePermission(AdminRole::Admin, AdminPermission::AdminAccess, $user))->toBeTrue()
        ->and($user->fresh()?->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('reports a catalog that is not initialized and changes nothing', function (): void {
    $actor = createAdminCommandActor();
    $user = createUser();

    Role::query()->where('name', AdminRole::AdminRolesReader->value)->delete();
    $missingRoleFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantRole($user, AdminRole::AdminRolesReader, $actor),
    );

    Role::query()->where('name', AdminRole::Admin->value)->delete();
    $missingCatalogFailure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantRole($user, AdminRole::AdminUsersReader, $actor),
    );

    expect($missingRoleFailure)->toBe(AdminAuthorizationFailure::RoleNotInitialized)
        ->and($missingCatalogFailure)->toBe(AdminAuthorizationFailure::CatalogNotInitialized)
        ->and($user->fresh()?->roles()->exists())->toBeFalse()
        ->and(adminAuthorizationActivityCount(ActivityLogAdminEvent::RoleGranted))->toBe(0);
});

it('reports a cataloged permission that is not initialized', function (): void {
    $user = createAdminCommandActor();
    Permission::query()->where('name', AdminPermission::AdminRolesUpdate->value)->delete();

    $failure = adminAuthorizationFailureOf(
        static fn (): bool => adminAuthorizationService()->grantPermission(AdminRole::AdminUsersReader, AdminPermission::AdminRolesUpdate, $user),
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

    adminAuthorizationService()->grantRole($user, AdminRole::AdminUsersReader, $actor);

    $adminGuardRole = Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->sole();

    expect($user->fresh()?->roles()->get()->modelKeys())->toBe([$adminGuardRole->getKey()]);
});

it('keeps the callers user instance in sync with the stored roles', function (): void {
    $actor = createAdminCommandActor();
    $user = User::query()->with('roles')->findOrFail(createUser()->id);

    adminAuthorizationService()->grantRole($user, AdminRole::AdminUsersReader, $actor);

    expect($user->hasRole(AdminRole::AdminUsersReader->value, 'admin'))->toBeTrue();
});
