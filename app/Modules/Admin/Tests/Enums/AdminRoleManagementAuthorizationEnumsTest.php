<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;

test('role management RBAC values retain their public identifiers', function (): void {
    expect([
        AdminPermission::AdminRolesViewAny->value,
        AdminPermission::AdminRolesView->value,
        AdminPermission::AdminRolesUpdate->value,
        AdminPermission::AdminRolesCreate->value,
        AdminPermission::AdminRolesDelete->value,
        AdminPermission::AdminUsersRoles->value,
        AdminRole::AdminRolesReader->value,
        AdminRole::AdminRolesManager->value,
    ])->toBe([
        'admin.roles.viewAny',
        'admin.roles.view',
        'admin.roles.update',
        'admin.roles.create',
        'admin.roles.delete',
        'admin.users.roles',
        'admin-roles-reader',
        'admin-roles-manager',
    ]);
});

test('unlisted role management RBAC values cannot be resolved', function (): void {
    expect([
        AdminPermission::tryFrom('admin.roles.restore'),
        AdminPermission::tryFrom('admin.roles.forceDelete'),
        AdminRole::tryFrom('admin-roles-owner'),
    ])->toBe([null, null, null]);
});

test('translation keys use the case name in snake case', function (): void {
    expect(AdminPermission::AdminUsersView->translationKey())->toBe('admin_users_view')
        ->and(AdminPermission::AdminUsersViewAny->translationKey())->toBe('admin_users_view_any')
        ->and(AdminRole::AdminRolesManager->translationKey())->toBe('admin_roles_manager');
});

test('every role and permission has a unique translation key', function (): void {
    $permissionKeys = array_map(
        static fn (AdminPermission $adminPermission): string => $adminPermission->translationKey(),
        AdminPermission::cases(),
    );
    $roleKeys = array_map(
        static fn (AdminRole $adminRole): string => $adminRole->translationKey(),
        AdminRole::cases(),
    );

    expect($permissionKeys)->toHaveSameSize(array_unique($permissionKeys))
        ->and($roleKeys)->toHaveSameSize(array_unique($roleKeys))
        ->and(array_filter($permissionKeys, static fn (string $key): bool => str_contains($key, '.')))
        ->toBeEmpty();
});

test('each permission belongs to the panel area named after admin', function (): void {
    expect(AdminPermission::AdminAccess->area())->toBe('access')
        ->and(AdminPermission::AdminDashboardView->area())->toBe('dashboard')
        ->and(AdminPermission::AdminUsersRoles->area())->toBe('users')
        ->and(AdminPermission::AdminRolesUpdate->area())->toBe('roles')
        ->and(array_values(array_unique(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->area(),
            AdminPermission::cases(),
        ))))->toBe(['access', 'dashboard', 'users', 'posts', 'activity', 'backups', 'announcements', 'roles']);
});

test('the entry permissions of the panel require nothing', function (AdminPermission $adminPermission): void {
    expect($adminPermission->requires())
        ->toBeEmpty();
})->with([
    AdminPermission::AdminAccess,
    AdminPermission::AdminDashboardView,
    AdminPermission::AdminUsersViewAny,
    AdminPermission::AdminBackupsView,
    AdminPermission::AdminRolesViewAny,
]);

test('each permission requires only permissions of its own area, never itself', function (): void {
    foreach (AdminPermission::cases() as $adminPermission) {
        expect($adminPermission->requires())->not->toContain($adminPermission);

        foreach ($adminPermission->requires() as $requiredPermission) {
            expect($requiredPermission->area())->toBe($adminPermission->area());
        }
    }
});

test('no permission depends on itself through its requirements', function (): void {
    foreach (AdminPermission::cases() as $adminPermission) {
        foreach ($adminPermission->requires() as $requiredPermission) {
            expect(AdminPermission::withRequirements([$requiredPermission]))->not->toContain($adminPermission);
        }
    }
});

test('a permission brings everything it needs in catalog order', function (): void {
    expect(AdminPermission::withRequirements([AdminPermission::AdminUsersSuspend]))->toBe([
        AdminPermission::AdminUsersViewAny,
        AdminPermission::AdminUsersView,
        AdminPermission::AdminUsersSuspend,
    ])->and(AdminPermission::withRequirements([AdminPermission::AdminUsersCreate]))->toBe([
        AdminPermission::AdminUsersViewAny,
        AdminPermission::AdminUsersCreate,
    ])->and(AdminPermission::withRequirements([
        AdminPermission::AdminRolesDelete,
        AdminPermission::AdminAccess,
        AdminPermission::AdminRolesView,
    ]))->toBe([
        AdminPermission::AdminAccess,
        AdminPermission::AdminRolesViewAny,
        AdminPermission::AdminRolesView,
        AdminPermission::AdminRolesDelete,
    ])->and(AdminPermission::withRequirements([]))
        ->toBeEmpty();
});

test('missing requirements lists only what the selection lacks', function (): void {
    expect(AdminPermission::missingRequirements([AdminPermission::AdminUsersView]))->toBe([AdminPermission::AdminUsersViewAny])
        ->and(AdminPermission::missingRequirements([AdminPermission::AdminAnnouncementsReview, AdminPermission::AdminAnnouncementsViewAny]))
        ->toBe([AdminPermission::AdminAnnouncementsView])
        ->and(AdminPermission::missingRequirements([AdminPermission::AdminUsersViewAny, AdminPermission::AdminUsersView]))
        ->toBeEmpty();
});

test('the dependents of a permission are the ones that need it', function (): void {
    expect(AdminPermission::AdminBackupsView->dependents())->toBe([
        AdminPermission::AdminBackupsCreate,
        AdminPermission::AdminBackupsDownload,
    ])->and(AdminPermission::AdminUsersView->dependents())->toBe([
        AdminPermission::AdminUsersSuspend,
        AdminPermission::AdminUsersReactivate,
        AdminPermission::AdminUsersRoles,
    ])->and(AdminPermission::AdminRolesDelete->dependents())
        ->toBeEmpty();

    foreach (AdminPermission::cases() as $adminPermission) {
        foreach ($adminPermission->dependents() as $dependentPermission) {
            expect(AdminPermission::withRequirements([$dependentPermission]))->toContain($adminPermission);
        }
    }
});

test('every seeded system role meets the requirements of its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $roles = Role::query()->admin()->with('permissions')->get();

    expect($roles)->toHaveCount(count(AdminRole::cases()));

    foreach ($roles as $role) {
        $adminPermissions = [];

        foreach ($role->permissions as $permission) {
            $adminPermissions[] = AdminPermission::from($permission->name);
        }

        expect(AdminPermission::missingRequirements($adminPermissions))->toBe([], $role->name);
    }
});
