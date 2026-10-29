<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Announcement\Models\Announcement;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
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
                || Gate::forUser($authenticatedUser)->allows('viewAny', Announcement::class)
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
                'label' => $this->text('users.label'),
                'description' => $this->text('users.description'),
                'icon' => Heroicon::OutlinedUsers,
                'url' => UserResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminPostsViewAny->value)) {
            $quickLinks[] = [
                'label' => $this->text('posts.label'),
                'description' => $this->text('posts.description'),
                'icon' => Heroicon::OutlinedRectangleStack,
                'url' => PostResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminActivityViewAny->value)) {
            $quickLinks[] = [
                'label' => $this->text('activity_log.label'),
                'description' => $this->text('activity_log.description'),
                'icon' => Heroicon::OutlinedClipboardDocumentList,
                'url' => ActivityLogResource::getUrl('index', panel: 'admin'),
            ];
        }

        if ($authenticatedUser->can(AdminPermission::AdminBackupsView->value)) {
            $quickLinks[] = [
                'label' => $this->text('backups.label'),
                'description' => $this->text('backups.description'),
                'icon' => Heroicon::OutlinedCircleStack,
                'url' => BackupManagement::getUrl(panel: 'admin'),
            ];
        }

        if (Gate::forUser($authenticatedUser)->allows('viewAny', Announcement::class)) {
            $quickLinks[] = [
                'label' => $this->text('announcements.label'),
                'description' => $this->text('announcements.description'),
                'icon' => Heroicon::OutlinedMegaphone,
                'url' => AnnouncementResource::getUrl('index', panel: 'admin'),
            ];
        }

        return $quickLinks;
    }

    /**
     * A text of the quick links, from `admin/dashboard.quick_links.links`.
     */
    private function text(string $key): string
    {
        return TranslatedText::of('admin/dashboard.quick_links.links.'.$key);
    }
}
