<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

use App\Support\Translation\TranslatedText;
use Illuminate\Support\Str;

enum AdminPermission: string
{
    case AdminAccess = 'admin.access';

    case AdminDashboardView = 'admin.dashboard.view';

    case AdminUsersViewAny = 'admin.users.viewAny';

    case AdminUsersView = 'admin.users.view';

    case AdminUsersCreate = 'admin.users.create';

    case AdminUsersSuspend = 'admin.users.suspend';

    case AdminUsersReactivate = 'admin.users.reactivate';

    case AdminUsersRoles = 'admin.users.roles';

    case AdminPostsViewAny = 'admin.posts.viewAny';

    case AdminPostsView = 'admin.posts.view';

    case AdminActivityViewAny = 'admin.activity.viewAny';

    case AdminActivityView = 'admin.activity.view';

    case AdminBackupsView = 'admin.backups.view';

    case AdminBackupsCreate = 'admin.backups.create';

    case AdminBackupsDownload = 'admin.backups.download';

    case AdminAnnouncementsViewAny = 'admin.announcements.viewAny';

    case AdminAnnouncementsView = 'admin.announcements.view';

    case AdminAnnouncementsReview = 'admin.announcements.review';

    case AdminAnnouncementsCreate = 'admin.announcements.create';

    case AdminAnnouncementsUpdate = 'admin.announcements.update';

    case AdminAnnouncementsDelete = 'admin.announcements.delete';

    case AdminRolesViewAny = 'admin.roles.viewAny';

    case AdminRolesView = 'admin.roles.view';

    case AdminRolesUpdate = 'admin.roles.update';

    /**
     * The key of this permission in `admin/roles.catalog.permissions`. The value has dots, which a
     * translation key would read as nesting, so the key is the case name in snake case.
     */
    public function translationKey(): string
    {
        return Str::snake($this->name);
    }

    public function label(): string
    {
        return TranslatedText::of('admin/roles.catalog.permissions.'.$this->translationKey().'.label');
    }

    public function description(): string
    {
        return TranslatedText::of('admin/roles.catalog.permissions.'.$this->translationKey().'.description');
    }

    /**
     * The panel area the permission belongs to, the segment after `admin.` (`users`, `backups`...).
     */
    public function area(): string
    {
        return Str::of($this->value)->after('admin.')->before('.')->toString();
    }
}
