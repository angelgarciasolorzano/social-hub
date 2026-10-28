<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Models\Announcement;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function tableActionAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The announcements list on the given tab with the given row action mounted.
 *
 * @return Testable<ListAnnouncements>
 */
function mountAnnouncementRowAction(string $actionName, Announcement $announcement, string $activeTab = 'all'): Testable
{
    return Livewire::test(ListAnnouncements::class)
        ->set('activeTab', $activeTab)
        ->call('loadTable')
        ->call('mountAction', $actionName, [], [
            'table' => true,
            'recordKey' => FilamentTableRecords::recordKey($announcement),
        ]);
}

/**
 * Whether the given list has an action mounted.
 *
 * @param  Testable<ListAnnouncements>  $testable
 */
function announcementListHasMountedAction(Testable $testable): bool
{
    return $testable->instance()->mountedActions !== [];
}

/**
 * How many audit rows the given admin event has.
 */
function announcementEventCount(ActivityLogAdminEvent $activityLogAdminEvent): int
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('does not offer duplicate or unpublish without the create and update permissions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsView);
    $announcement = tableActionAnnouncementFactory()->active()->createOne();
    $endsAt = $announcement->ends_at?->toDateTimeString();

    $testable = mountAnnouncementRowAction('unpublish', $announcement)->call('callMountedAction');
    $duplicate = mountAnnouncementRowAction('duplicate', $announcement)->call('callMountedAction');

    expect(announcementListHasMountedAction($testable))->toBeFalse()
        ->and(announcementListHasMountedAction($duplicate))->toBeFalse()
        ->and($announcement->refresh()->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and(Announcement::query()->count())->toBe(1)
        ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementUnpublished))->toBe(0);
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('duplicates an announcement as a copy scheduled for tomorrow and opens it', function (): void {
        $this->freezeSecond();
        $announcement = tableActionAnnouncementFactory()->active()->withButton()->createOne();

        $testable = mountAnnouncementRowAction('duplicate', $announcement)->call('callMountedAction');

        $copy = Announcement::query()->whereKeyNot($announcement->id)->sole();

        $testable->assertRedirect(AnnouncementResource::getUrl('edit', ['record' => $copy]));

        expect($copy->title)->toBe($announcement->title)
            ->and($copy->button_url)->toBe($announcement->button_url)
            ->and($copy->starts_at->equalTo(now()->addDay()))->toBeTrue()
            ->and($copy->ends_at)->toBeNull()
            ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementDuplicated))->toBe(1);
    });

    it('unpublishes a running announcement, which moves it to the expired tab', function (): void {
        $announcement = tableActionAnnouncementFactory()->active()->createOne();

        mountAnnouncementRowAction('unpublish', $announcement, 'active')->call('callMountedAction');

        $testable = Livewire::test(ListAnnouncements::class)->set('activeTab', 'expired')->call('loadTable');

        expect($announcement->refresh()->isRunning())->toBeFalse()
            ->and(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Announcement::class))
            ->toBe([FilamentTableRecords::recordKey($announcement)])
            ->and(announcementEventCount(ActivityLogAdminEvent::AnnouncementUnpublished))->toBe(1);
    });

    it('does not offer unpublish for an announcement that is not running', function (AnnouncementFactory $announcementFactory): void {
        $announcement = $announcementFactory->createOne();

        $testable = mountAnnouncementRowAction('unpublish', $announcement);

        expect(announcementListHasMountedAction($testable))->toBeFalse();
    })->with([
        'scheduled' => fn (): AnnouncementFactory => tableActionAnnouncementFactory()->scheduled(),
        'expired' => fn (): AnnouncementFactory => tableActionAnnouncementFactory()->expired(),
    ]);

    it('previews the banner with its sanitized message and without dangerous HTML', function (): void {
        $announcement = tableActionAnnouncementFactory()->withButton()->createOne([
            'body' => '<p>Mensaje <strong>importante</strong></p><script>alert("preview")</script><img src="x" onerror="alert(1)">',
        ]);

        $testable = mountAnnouncementRowAction('preview', $announcement);

        expect(announcementListHasMountedAction($testable))->toBeTrue();

        $testable->__call('assertMountedActionModalSee', [[$announcement->title, (string) $announcement->button_label]]);
        $testable->__call('assertMountedActionModalSeeHtml', ['<strong>importante</strong>']);
        $testable->__call('assertMountedActionModalDontSeeHtml', [['<script>alert("preview")', 'onerror=']]);
    });
});
