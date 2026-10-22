<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;

test('backup RBAC values retain their public identifiers', function (): void {
    expect([
        AdminPermission::AdminBackupsView->value,
        AdminPermission::AdminBackupsCreate->value,
        AdminPermission::AdminBackupsDownload->value,
        AdminRole::AdminBackupsReader->value,
    ])->toBe([
        'admin.backups.view',
        'admin.backups.create',
        'admin.backups.download',
        'admin-backups-reader',
    ]);
});

test('unlisted backup RBAC values cannot be resolved', function (): void {
    expect([
        AdminPermission::tryFrom('admin.backups.restore'),
        AdminRole::tryFrom('admin-backups-operator'),
    ])->toBe([null, null]);
});
