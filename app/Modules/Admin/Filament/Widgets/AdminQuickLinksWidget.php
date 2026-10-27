<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\User\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Override;

class AdminQuickLinksWidget extends Widget
{
    #[Override]
    protected string $view = 'admin::filament.widgets.admin-quick-links';

    #[Override]
    protected int|string|array $columnSpan = 1;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && (
                $authenticatedUser->can(AdminPermission::AdminUsersViewAny->value)
                || $authenticatedUser->can(AdminPermission::AdminPostsViewAny->value)
                || $authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)
                || $authenticatedUser->can(AdminPermission::AdminBackupsView->value)
            );
    }

    /**
     * @return list<array{label: string, description: string, icon: Heroicon, url: string}>
     */
    public function getQuickLinks(): array
    {
        $authenticatedUser = auth('admin')->user();

        if (! $authenticatedUser instanceof User) {
            return [];
        }

        $quickLinks = [];

        if ($authenticatedUser->can(AdminPermission::AdminUsersViewAny->value)) {
            $quickLinks[] = [
                'label' => 'Users',
                'description' => 'Review accounts and account status',
                'icon' => Heroicon::OutlinedUsers,
                'url' => UserResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminPostsViewAny->value)) {
            $quickLinks[] = [
                'label' => 'Posts',
                'description' => 'Review posts and comments',
                'icon' => Heroicon::OutlinedRectangleStack,
                'url' => PostResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)) {
            $quickLinks[] = [
                'label' => 'Activity log',
                'description' => 'Review security and admin events',
                'icon' => Heroicon::OutlinedClipboardDocumentList,
                'url' => ActivityLogResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminBackupsView->value)) {
            $quickLinks[] = [
                'label' => 'Backups',
                'description' => 'Check backup availability',
                'icon' => Heroicon::OutlinedCircleStack,
                'url' => BackupManagement::getUrl(panel: 'admin'),
            ];
        }

        return $quickLinks;
    }
}
