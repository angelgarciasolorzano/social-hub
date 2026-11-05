<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

use App\Support\Translation\TranslatedText;

/**
 * Why `AdminAuthorizationService` refused a change. The console commands turn each case into their
 * own messages; the panel shows `message()`.
 */
enum AdminAuthorizationFailure: string
{
    case CatalogNotInitialized = 'catalog_not_initialized';

    case RoleNotInitialized = 'role_not_initialized';

    case PermissionNotInitialized = 'permission_not_initialized';

    case LastAdminAccess = 'last_admin_access';

    case ActorWouldLoseAccess = 'actor_would_lose_access';

    case RoleLocked = 'role_locked';

    case SystemRoleProtected = 'system_role_protected';

    case RoleHasUsers = 'role_has_users';

    case RoleLabelTaken = 'role_label_taken';

    case PermissionDependencyMissing = 'permission_dependency_missing';

    public function message(): string
    {
        return TranslatedText::of('admin/roles.failures.'.$this->value);
    }
}
