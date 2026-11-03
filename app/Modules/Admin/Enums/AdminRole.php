<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

use App\Support\Translation\TranslatedText;
use Illuminate\Support\Str;

enum AdminRole: string
{
    case Admin = 'admin';

    case AdminUsersReader = 'admin-users-reader';

    case AdminUsersProvisioner = 'admin-users-provisioner';

    case AdminUsersModerator = 'admin-users-moderator';

    case AdminPostsReader = 'admin-posts-reader';

    case AdminDashboardReader = 'admin-dashboard-reader';

    case AdminActivityReader = 'admin-activity-reader';

    case AdminBackupsReader = 'admin-backups-reader';

    case AdminBackupsOperator = 'admin-backups-operator';

    case AdminAnnouncementsManager = 'admin-announcements-manager';

    case AdminAnnouncementsReviewer = 'admin-announcements-reviewer';

    case AdminRolesReader = 'admin-roles-reader';

    case AdminRolesManager = 'admin-roles-manager';

    /**
     * The key of this role in `admin/roles.catalog.roles`: the case name in snake case, the same
     * convention as `AdminPermission::translationKey()`.
     */
    public function translationKey(): string
    {
        return Str::snake($this->name);
    }

    public function label(): string
    {
        return TranslatedText::of('admin/roles.catalog.roles.'.$this->translationKey().'.label');
    }

    public function description(): string
    {
        return TranslatedText::of('admin/roles.catalog.roles.'.$this->translationKey().'.description');
    }
}
