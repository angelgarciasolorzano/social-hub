<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminPermission: string
{
    case AdminAccess = 'admin.access';

    case AdminDashboardView = 'admin.dashboard.view';

    case AdminUsersViewAny = 'admin.users.viewAny';

    case AdminUsersView = 'admin.users.view';

    case AdminPostsViewAny = 'admin.posts.viewAny';

    case AdminPostsView = 'admin.posts.view';
}
