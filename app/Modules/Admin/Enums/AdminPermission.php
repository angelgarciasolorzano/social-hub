<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminPermission: string
{
    case AdminAccess = 'admin.access';

    case DashboardView = 'admin.dashboard.view';

    case UsersViewAny = 'admin.users.viewAny';

    case UsersView = 'admin.users.view';

    case PostsViewAny = 'admin.posts.viewAny';

    case PostsView = 'admin.posts.view';
}
