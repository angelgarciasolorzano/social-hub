<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;

test('role management RBAC values retain their public identifiers', function (): void {
    expect([
        AdminPermission::AdminRolesViewAny->value,
        AdminPermission::AdminRolesView->value,
        AdminPermission::AdminRolesUpdate->value,
        AdminPermission::AdminUsersRoles->value,
        AdminRole::AdminRolesReader->value,
        AdminRole::AdminRolesManager->value,
    ])->toBe([
        'admin.roles.viewAny',
        'admin.roles.view',
        'admin.roles.update',
        'admin.users.roles',
        'admin-roles-reader',
        'admin-roles-manager',
    ]);
});

test('unlisted role management RBAC values cannot be resolved', function (): void {
    expect([
        AdminPermission::tryFrom('admin.roles.delete'),
        AdminPermission::tryFrom('admin.roles.create'),
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
