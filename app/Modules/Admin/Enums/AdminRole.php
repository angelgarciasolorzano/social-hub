<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminRole: string
{
    case Admin = 'admin';

    case UsersReader = 'admin-users-reader';

    case PostsReader = 'admin-posts-reader';

    case DashboardReader = 'admin-dashboard-reader';
}
