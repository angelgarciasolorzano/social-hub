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

    case AdminRolesCreate = 'admin.roles.create';

    case AdminRolesDelete = 'admin.roles.delete';

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

    /**
     * The permissions this one needs to be usable: seeing a record needs its list, and every write
     * or action of an area needs both. Creating users only needs the list, where the invite lives.
     *
     * @return list<AdminPermission>
     */
    public function requires(): array
    {
        return match ($this) {
            self::AdminAccess,
            self::AdminDashboardView,
            self::AdminUsersViewAny,
            self::AdminPostsViewAny,
            self::AdminActivityViewAny,
            self::AdminBackupsView,
            self::AdminAnnouncementsViewAny,
            self::AdminRolesViewAny => [],
            self::AdminUsersView,
            self::AdminUsersCreate => [self::AdminUsersViewAny],
            self::AdminUsersSuspend,
            self::AdminUsersReactivate,
            self::AdminUsersRoles => [self::AdminUsersViewAny, self::AdminUsersView],
            self::AdminPostsView => [self::AdminPostsViewAny],
            self::AdminActivityView => [self::AdminActivityViewAny],
            self::AdminBackupsCreate,
            self::AdminBackupsDownload => [self::AdminBackupsView],
            self::AdminAnnouncementsView => [self::AdminAnnouncementsViewAny],
            self::AdminAnnouncementsReview,
            self::AdminAnnouncementsCreate,
            self::AdminAnnouncementsUpdate,
            self::AdminAnnouncementsDelete => [self::AdminAnnouncementsViewAny, self::AdminAnnouncementsView],
            self::AdminRolesView => [self::AdminRolesViewAny],
            self::AdminRolesUpdate,
            self::AdminRolesCreate,
            self::AdminRolesDelete => [self::AdminRolesViewAny, self::AdminRolesView],
        };
    }

    /**
     * The given permissions plus everything they need, transitively, in catalog order.
     *
     * @param  list<AdminPermission>  $adminPermissions
     * @return list<AdminPermission>
     */
    public static function withRequirements(array $adminPermissions): array
    {
        $selected = [];
        $pending = $adminPermissions;

        while ($pending !== []) {
            $adminPermission = array_pop($pending);

            if (isset($selected[$adminPermission->value])) {
                continue;
            }

            $selected[$adminPermission->value] = true;

            foreach ($adminPermission->requires() as $requiredPermission) {
                $pending[] = $requiredPermission;
            }
        }

        return array_values(array_filter(
            self::cases(),
            static fn (AdminPermission $adminPermission): bool => isset($selected[$adminPermission->value]),
        ));
    }

    /**
     * The permissions the given ones need and do not include, in catalog order.
     *
     * @param  list<AdminPermission>  $adminPermissions
     * @return list<AdminPermission>
     */
    public static function missingRequirements(array $adminPermissions): array
    {
        return array_values(array_filter(
            self::withRequirements($adminPermissions),
            static fn (AdminPermission $adminPermission): bool => ! in_array($adminPermission, $adminPermissions, true),
        ));
    }

    /**
     * The permissions that need this one, transitively, in catalog order: unchecking this one in a
     * form unchecks them too.
     *
     * @return list<AdminPermission>
     */
    public function dependents(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (AdminPermission $adminPermission): bool => $adminPermission !== $this
                && in_array($this, self::withRequirements([$adminPermission]), true),
        ));
    }
}
