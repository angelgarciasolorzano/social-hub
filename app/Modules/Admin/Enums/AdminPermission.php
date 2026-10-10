<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminPermission: string
{
    case AdminAccess = 'admin.access';

    case UsersViewAny = 'users.viewAny';

    case UsersView = 'users.view';
}
