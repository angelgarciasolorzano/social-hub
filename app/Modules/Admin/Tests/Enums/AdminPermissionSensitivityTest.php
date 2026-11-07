<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;

/**
 * The permissions that give control over other administrators, their access or exported data.
 *
 * @return list<AdminPermission>
 */
function expectedSensitivePermissions(): array
{
    return [
        AdminPermission::AdminUsersCreate,
        AdminPermission::AdminUsersSuspend,
        AdminPermission::AdminUsersReactivate,
        AdminPermission::AdminUsersRoles,
        AdminPermission::AdminBackupsDownload,
        AdminPermission::AdminRolesUpdate,
        AdminPermission::AdminRolesCreate,
        AdminPermission::AdminRolesDelete,
    ];
}

it('marks as sensitive exactly the permissions that control other administrators or exported data', function (AdminPermission $adminPermission): void {
    expect($adminPermission->isSensitive())->toBe(in_array($adminPermission, expectedSensitivePermissions(), true));
})->with(AdminPermission::cases());

it('does not mark panel access as sensitive', function (): void {
    expect(AdminPermission::AdminAccess->isSensitive())->toBeFalse();
});

it('lists the sensitive permissions once each, in catalog order', function (): void {
    $catalogOrder = array_values(array_filter(
        AdminPermission::cases(),
        static fn (AdminPermission $adminPermission): bool => in_array($adminPermission, expectedSensitivePermissions(), true),
    ));

    expect(AdminPermission::sensitive())->toBe($catalogOrder)
        ->and(AdminPermission::sensitive())->toHaveCount(8);
});
