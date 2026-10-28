<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcements list with its table loaded, on the given tab.
 *
 * @return Testable<ListAnnouncements>
 */
function announcementsListPage(string $activeTab = 'active'): Testable
{
    return Livewire::test(ListAnnouncements::class)
        ->set('activeTab', $activeTab)
        ->call('loadTable');
}

/**
 * The keys of the announcements the list shows on the given tab.
 *
 * @return list<string>
 */
function listedAnnouncementKeys(string $activeTab = 'active'): array
{
    return FilamentTableRecords::visibleRecordKeys(
        FilamentTableRecords::table(announcementsListPage($activeTab)),
        Announcement::class,
    );
}

/**
 * The announcement factory, typed for static analysis.
 */
function listAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Count the queries run while rendering the list; call it once first so permission
 * and schema caches are warm and both measurements compare rows only.
 */
function countAnnouncementsListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    announcementsListPage('all');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the list to an administrator without admin.announcements.viewAny', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminPostsReader);

    Livewire::test(ListAnnouncements::class)->assertForbidden();

    expect(AnnouncementResource::canAccess())->toBeFalse();
});

it('hides the create button from an administrator who can only list announcements', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin);
    grantAdminPermissionThroughTestRole($user, AdminPermission::AdminAnnouncementsViewAny);

    Livewire::test(ListAnnouncements::class)
        ->assertOk()
        ->assertDontSee('New announcement');
});

describe('with the announcements manager role', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $testCase */
        $testCase = $this;

        AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    });

    it('defers loading the table and opens on the active tab', function (): void {
        $testable = Livewire::test(ListAnnouncements::class)->assertOk();
        $table = FilamentTableRecords::table($testable)->getTable();

        expect(AnnouncementResource::canAccess())->toBeTrue()
            ->and($testable->get('activeTab'))->toBe('active')
            ->and($table->isLoadingDeferred())->toBeTrue()
            ->and($table->hasLoadingSkeleton())->toBeTrue();
    });

    it('shows the create button', function (): void {
        Livewire::test(ListAnnouncements::class)->assertSee('New announcement');
    });

    it('shows on each tab only the announcements of that schedule', function (): void {
        $announcement = listAnnouncementFactory()->scheduled()->createOne();
        $active = listAnnouncementFactory()->active()->createOne();
        $withoutEnd = listAnnouncementFactory()->createOne(['starts_at' => now()->subDay(), 'ends_at' => null]);
        $expired = listAnnouncementFactory()->expired()->createOne();

        expect(listedAnnouncementKeys('scheduled'))->toBe([FilamentTableRecords::recordKey($announcement)])
            ->and(listedAnnouncementKeys('active'))->toEqualCanonicalizing([
                FilamentTableRecords::recordKey($active),
                FilamentTableRecords::recordKey($withoutEnd),
            ])
            ->and(listedAnnouncementKeys('expired'))->toBe([FilamentTableRecords::recordKey($expired)])
            ->and(listedAnnouncementKeys('all'))->toHaveCount(4);
    });

    it('filters the list by type and by audience', function (): void {
        $announcement = listAnnouncementFactory()->ofType(AnnouncementType::Critical)->createOne();
        $withoutTwoFactor = listAnnouncementFactory()
            ->ofType(AnnouncementType::Info)
            ->forAudience(AnnouncementAudience::WithoutTwoFactor)
            ->createOne();

        $testable = announcementsListPage()->set('tableFilters.type', ['value' => AnnouncementType::Critical->value]);
        $byAudience = announcementsListPage()->set('tableFilters.audience', ['value' => AnnouncementAudience::WithoutTwoFactor->value]);

        expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Announcement::class))
            ->toBe([FilamentTableRecords::recordKey($announcement)])
            ->and(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($byAudience), Announcement::class))
            ->toBe([FilamentTableRecords::recordKey($withoutTwoFactor)]);
    });

    it('shows the type label, the author, a deleted author and a missing end date', function (): void {
        $user = createUser();
        listAnnouncementFactory()->ofType(AnnouncementType::Critical)->createdBy($user)->createOne();
        listAnnouncementFactory()->createOne(['created_by' => null, 'ends_at' => null]);

        announcementsListPage()
            ->assertSee('Critical')
            ->assertSee($user->name)
            ->assertSee('Deleted user')
            ->assertSee('No end date');
    });

    it('does not add queries per row', function (): void {
        listAnnouncementFactory()->count(3)->create();

        countAnnouncementsListQueries();
        $queriesWithFewAnnouncements = countAnnouncementsListQueries();

        listAnnouncementFactory()->count(17)->create();

        expect(countAnnouncementsListQueries())->toBe($queriesWithFewAnnouncements);
    });

    it('lists only the running dialogs on the active dialogs tab and counts them on its badge', function (): void {
        $announcement = listAnnouncementFactory()->asDialog()->active()->createOne();
        $withoutEnd = listAnnouncementFactory()->asDialog()->createOne(['starts_at' => now()->subDay(), 'ends_at' => null]);
        listAnnouncementFactory()->asDialog()->scheduled()->createOne();
        listAnnouncementFactory()->asDialog()->expired()->createOne();
        listAnnouncementFactory()->active()->createOne();

        $testable = announcementsListPage('active_dialogs');

        expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Announcement::class))
            ->toEqualCanonicalizing([FilamentTableRecords::recordKey($announcement), FilamentTableRecords::recordKey($withoutEnd)])
            ->and($testable->instance()->getCachedTabs()['active_dialogs']->getBadge())->toBe('2');
    });

    it('filters the list by how announcements are shown', function (): void {
        $announcement = listAnnouncementFactory()->asDialog()->createOne();
        listAnnouncementFactory()->createOne();

        $testable = announcementsListPage()->set('tableFilters.display', ['value' => AnnouncementDisplay::Dialog->value]);

        expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), Announcement::class))
            ->toBe([FilamentTableRecords::recordKey($announcement)]);
    });

    it('shows how each announcement is shown', function (): void {
        listAnnouncementFactory()->asDialog()->createOne();

        announcementsListPage()
            ->assertSee('Shown as')
            ->assertSee('Dialog');
    });
});
