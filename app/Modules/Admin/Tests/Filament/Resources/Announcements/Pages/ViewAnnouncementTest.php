<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function viewAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The detail page of the given announcement.
 *
 * @return Testable<ViewAnnouncement>
 */
function viewAnnouncementPage(Announcement $announcement): Testable
{
    return Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getKey()]);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the detail to an administrator without admin.announcements.view', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);

    viewAnnouncementPage(viewAnnouncementFactory()->createOne())->assertForbidden();
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('shows the content, display settings, button and author of the announcement', function (): void {
        Storage::fake('public');
        $user = createUser();
        $announcement = viewAnnouncementFactory()
            ->ofType(AnnouncementType::Critical)
            ->forAudience(AnnouncementAudience::WithoutTwoFactor)
            ->withButton()
            ->notDismissible()
            ->createdBy($user)
            ->createOne(['body' => '<p>Activa la <strong>verificación en dos pasos</strong>.</p>']);
        $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
            ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

        viewAnnouncementPage($announcement)
            ->assertOk()
            ->assertSee($announcement->title)
            ->assertSeeHtml('<strong>verificación en dos pasos</strong>')
            ->assertSee('Critical')
            ->assertSee('Users without two-factor authentication')
            ->assertSee((string) $announcement->button_label)
            ->assertSee((string) $announcement->button_url)
            ->assertSee($user->name)
            ->assertDontSee('No image');
    });

    it('shows how the announcement is presented', function (string $display, string $label, string $otherLabel): void {
        $announcement = viewAnnouncementFactory()->createOne(['display' => $display]);

        viewAnnouncementPage($announcement)
            ->assertSee('Shown as')
            ->assertSee($label)
            ->assertDontSee($otherLabel);
    })->with([
        'toast' => ['toast', 'Toast', 'Dialog'],
        'dialog' => ['dialog', 'Dialog', 'Toast'],
    ]);

    it('shows placeholders for a deleted author, no end date, no button and no image', function (): void {
        $announcement = viewAnnouncementFactory()->createOne(['created_by' => null, 'ends_at' => null]);

        viewAnnouncementPage($announcement)
            ->assertSee('Deleted user')
            ->assertSee('No end date')
            ->assertSee('No button')
            ->assertSee('No image');
    });

    it('never renders dangerous HTML from the message', function (): void {
        $announcement = viewAnnouncementFactory()->createOne([
            'body' => '<p>Seguro</p><script>alert("script")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">enlace</a>',
        ]);

        viewAnnouncementPage($announcement)
            ->assertSee('Seguro')
            ->assertDontSeeHtml('<script>alert("script")')
            ->assertDontSeeHtml('onerror=')
            ->assertDontSeeHtml('javascript:alert(2)');
    });
});
