<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Pages\Backups\BackupManagement;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Widgets\AdminQuickLinksWidget;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Create an administrator with the requested section permissions.
 */
function adminQuickLinksUser(AdminPermission ...$adminPermissions): User
{
    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    if ($adminPermissions !== []) {
        $user->givePermissionTo(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            $adminPermissions,
        ));
    }

    return $user;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('registers quick links in place of the Filament information widget', function (): void {
    $widgets = Filament::getPanel('admin')->getWidgets();

    expect($widgets)
        ->toContain(AccountWidget::class, AdminQuickLinksWidget::class)
        ->not->toContain(FilamentInfoWidget::class);
});

it('shows links for each section the administrator can access', function (): void {
    $user = adminQuickLinksUser(
        AdminPermission::AdminUsersViewAny,
        AdminPermission::AdminPostsViewAny,
        AdminPermission::AdminActivityViewAny,
        AdminPermission::AdminBackupsView,
    );
    $this->actingAs($user, 'admin');

    Livewire::test(AdminQuickLinksWidget::class)
        ->assertOk()
        ->assertSeeText('Quick access')
        ->assertSeeText('Users')
        ->assertSeeText('Posts')
        ->assertSeeText('Activity log')
        ->assertSeeText('Backups')
        ->assertSee(UserResource::getUrl('index', panel: 'admin'))
        ->assertSee(PostResource::getUrl('index', panel: 'admin'))
        ->assertSee(ActivityLogResource::getUrl('index', panel: 'admin'))
        ->assertSee(BackupManagement::getUrl(panel: 'admin'))
        ->assertSee('aria-label="Dashboard quick links"', false)
        ->assertSee('<svg', false);
});

it('does not show links for sections without view permission', function (): void {
    $user = adminQuickLinksUser(AdminPermission::AdminUsersViewAny);
    $this->actingAs($user, 'admin');

    Livewire::test(AdminQuickLinksWidget::class)
        ->assertOk()
        ->assertSeeText('Users')
        ->assertDontSeeText('Posts')
        ->assertDontSeeText('Activity log')
        ->assertDontSeeText('Backups')
        ->assertSee(UserResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(PostResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(ActivityLogResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(BackupManagement::getUrl(panel: 'admin'));
});

it('shows the announcements link to an administrator who can list announcements', function (): void {
    $user = adminQuickLinksUser(AdminPermission::AdminAnnouncementsViewAny);
    $this->actingAs($user, 'admin');

    Livewire::test(AdminQuickLinksWidget::class)
        ->assertOk()
        ->assertSeeText('Announcements')
        ->assertSee(AnnouncementResource::getUrl('index', panel: 'admin'))
        ->assertDontSee(UserResource::getUrl('index', panel: 'admin'));
});

it('hides the announcements link without admin.announcements.viewAny', function (): void {
    $user = adminQuickLinksUser(AdminPermission::AdminUsersViewAny);
    $this->actingAs($user, 'admin');

    Livewire::test(AdminQuickLinksWidget::class)
        ->assertOk()
        ->assertDontSeeText('Announcements')
        ->assertDontSee(AnnouncementResource::getUrl('index', panel: 'admin'));
});

it('denies the widget to administrators without section access', function (): void {
    $user = adminQuickLinksUser();
    $this->actingAs($user, 'admin');

    Livewire::test(AdminQuickLinksWidget::class)->assertForbidden();
});
