<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

enum AdminRole: string
{
    case Admin = 'admin';

    case AdminUsersReader = 'admin-users-reader';

    case AdminUsersProvisioner = 'admin-users-provisioner';

    case AdminPostsReader = 'admin-posts-reader';

    case AdminDashboardReader = 'admin-dashboard-reader';

    case AdminActivityReader = 'admin-activity-reader';

    case AdminBackupsReader = 'admin-backups-reader';

    case AdminBackupsOperator = 'admin-backups-operator';
}
