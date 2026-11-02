<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function announcementStatusTableFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The announcements table with all schedule tabs included.
 *
 * @return Testable<ListAnnouncements>
 */
function announcementStatusTablePage(): Testable
{
    return Livewire::test(ListAnnouncements::class)
        ->set('activeTab', 'all')
        ->call('loadTable');
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
});

it('renders every announcement status as a localized badge', function (string $locale, string $columnLabel, array $expectedStatusLabels): void {
    $originalLocale = app()->getLocale();

    try {
        app()->setLocale($locale);

        foreach (AnnouncementStatus::cases() as $announcementStatus) {
            announcementStatusTableFactory()
                ->withStatus($announcementStatus)
                ->createOne();
        }

        $testable = announcementStatusTablePage();
        $testable->assertSeeText($columnLabel);

        foreach ($expectedStatusLabels as $expectedStatusLabel) {
            $testable->assertSeeText($expectedStatusLabel);
        }
    } finally {
        app()->setLocale($originalLocale);
    }
})->with([
    'English' => [
        'en',
        'Status',
        ['Draft', 'In review', 'Approved', 'Rejected'],
    ],
    'Spanish' => [
        'es',
        'Estado',
        ['Borrador', 'En revisión', 'Aprobado', 'Rechazado'],
    ],
]);

it('filters announcements by each stored status value', function (AnnouncementStatus $selectedStatus): void {
    $selectedAnnouncement = announcementStatusTableFactory()
        ->withStatus($selectedStatus)
        ->createOne();

    foreach (AnnouncementStatus::cases() as $announcementStatus) {
        if ($announcementStatus === $selectedStatus) {
            continue;
        }

        announcementStatusTableFactory()
            ->withStatus($announcementStatus)
            ->createOne();
    }

    $testable = announcementStatusTablePage()
        ->set('tableFilters.status', ['value' => $selectedStatus->value]);

    expect(FilamentTableRecords::visibleRecordKeys(
        FilamentTableRecords::table($testable),
        Announcement::class,
    ))->toBe([FilamentTableRecords::recordKey($selectedAnnouncement)]);
})->with([
    'draft' => AnnouncementStatus::Draft,
    'in review' => AnnouncementStatus::InReview,
    'approved' => AnnouncementStatus::Approved,
    'rejected' => AnnouncementStatus::Rejected,
]);

it('chooses the badge color from each announcement status case', function (AnnouncementStatus $announcementStatus, string $expectedColor): void {
    expect(AnnouncementsTable::statusColor($announcementStatus))->toBe($expectedColor);
})->with([
    'draft' => [AnnouncementStatus::Draft, 'gray'],
    'in review' => [AnnouncementStatus::InReview, 'warning'],
    'approved' => [AnnouncementStatus::Approved, 'success'],
    'rejected' => [AnnouncementStatus::Rejected, 'danger'],
]);

it('does not map a null or unknown raw status to an announcement label', function (): void {
    $announcementWithNullStatus = new Announcement;
    $announcementWithNullStatus->setRawAttributes(['status' => null], true);

    $announcementWithUnknownStatus = new Announcement;
    $announcementWithUnknownStatus->setRawAttributes(['status' => 'unknown'], true);

    foreach ([$announcementWithNullStatus, $announcementWithUnknownStatus] as $announcement) {
        $announcementStatus = AnnouncementsTable::statusForRecord($announcement);

        expect($announcementStatus)->toBeNull()
            ->and(AnnouncementsTable::statusLabel($announcementStatus))->toBeNull()
            ->and(AnnouncementsTable::statusColor($announcementStatus))->toBe('gray');
    }
});
