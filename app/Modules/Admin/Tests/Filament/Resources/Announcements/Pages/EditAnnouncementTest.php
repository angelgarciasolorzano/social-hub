<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function editAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The edit page of the given announcement.
 *
 * @return Testable<EditAnnouncement>
 */
function editAnnouncementPage(Announcement $announcement): Testable
{
    return Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()]);
}

/**
 * The audit rows recorded for the given admin event.
 *
 * @return array<int, ActivityLog>
 */
function editAnnouncementActivities(ActivityLogAdminEvent $activityLogAdminEvent): array
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->get()->all();
}

/**
 * Whether the delete action of the edit page is shown.
 *
 * @param  Testable<EditAnnouncement>  $testable
 */
function editPageShowsDeleteAction(Testable $testable): bool
{
    return $testable->instance()->getAction('delete')?->isVisible() ?? false;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the edit page to an administrator without admin.announcements.update', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);

    editAnnouncementPage(editAnnouncementFactory()->createOne())->assertForbidden();
});

it('hides the delete action and deletes nothing without admin.announcements.delete', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsUpdate);
    $announcement = editAnnouncementFactory()->createOne();

    $testable = editAnnouncementPage($announcement)->assertOk();

    expect(editPageShowsDeleteAction($testable))->toBeFalse();

    $testable->call('mountAction', 'delete')->call('callMountedAction');

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeTrue()
        ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementDeleted))->toBeEmpty();
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('fills the form from the announcement, with the button toggle on when it has one', function (): void {
        $announcement = editAnnouncementFactory()->withButton()->createOne();

        $testable = editAnnouncementPage($announcement)->assertOk();

        expect($testable->get('data.title'))->toBe($announcement->title)
            ->and($testable->get('data.has_button'))->toBeTrue()
            ->and($testable->get('data.button_url'))->toBe($announcement->button_url)
            ->and(editPageShowsDeleteAction($testable))->toBeTrue();
    });

    it('saves the changes and audits only the names of the changed attributes', function (): void {
        $announcement = editAnnouncementFactory()->ofType(AnnouncementType::Info)->createOne();

        editAnnouncementPage($announcement)
            ->set('data.title', 'Título corregido')
            ->set('data.type', AnnouncementType::Critical->value)
            ->call('save')
            ->assertHasNoErrors();

        $activities = editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

        expect($announcement->refresh()->title)->toBe('Título corregido')
            ->and($announcement->type)->toBe(AnnouncementType::Critical)
            ->and($activities)->toHaveCount(1)
            ->and($activities[0]->properties?->get('changed_attributes'))->toEqualCanonicalizing(['title', 'type']);
    });

    it('records nothing when saving without changes', function (): void {
        $announcement = editAnnouncementFactory()->withButton()->createOne();

        editAnnouncementPage($announcement)
            ->call('save')
            ->assertHasNoErrors();

        expect(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty();
    });

    it('removes the button when has button is switched off', function (): void {
        $announcement = editAnnouncementFactory()->withButton()->createOne();

        editAnnouncementPage($announcement)
            ->set('data.has_button', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->button_label)->toBeNull()
            ->and($announcement->button_url)->toBeNull();
    });

    it('rejects invalid changes without saving them', function (): void {
        $announcement = editAnnouncementFactory()->createOne();

        editAnnouncementPage($announcement)
            ->set('data.title', '')
            ->call('save')
            ->assertHasErrors(['data.title']);

        expect($announcement->refresh()->title)->not->toBeEmpty()
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty();
    });

    it('deletes the announcement through the service and returns to the list', function (): void {
        $announcement = editAnnouncementFactory()->createOne();

        editAnnouncementPage($announcement)
            ->call('mountAction', 'delete')
            ->call('callMountedAction')
            ->assertRedirect(ListAnnouncements::getUrl());

        expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeFalse()
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementDeleted))->toHaveCount(1);
    });
});
