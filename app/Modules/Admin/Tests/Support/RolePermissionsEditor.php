<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use App\Modules\Admin\Filament\Concerns\EditsRolePermissions;

/**
 * The role permission editor with its methods made public, so tests call them as the role pages do.
 */
final class RolePermissionsEditor
{
    use EditsRolePermissions {
        permissionCheckboxLists as public;
        permissionsByArea as public;
        selectedPermissions as public;
        applyPermissionDependencies as public;
        permissionChangeSummary as public;
        permissionSelectionOverview as public;
        sensitivePermissionsText as public;
    }
}
