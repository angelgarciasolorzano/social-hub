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

    public function message(): string
    {
        return TranslatedText::of('admin/roles.failures.'.$this->value);
    }
}
