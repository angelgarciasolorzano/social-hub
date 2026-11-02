<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\AnnouncementResource;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Admin\Filament\Resources\Announcements\RelationManagers\AnnouncementReviewsRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Database\Factories\AnnouncementReviewFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

/**
 * The review history table for the given announcement.
 *
 * @return Testable<Component>
 */
function announcementReviewsRelationManagerFor(Announcement $announcement): Testable
{
    return Livewire::test(AnnouncementReviewsRelationManager::class, [
        'ownerRecord' => $announcement,
        'pageClass' => ViewAnnouncement::class,
    ]);
}

/**
 * The announcement factory, typed for static analysis.
 */
function announcementFactoryForReviewHistory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The review factory, typed for static analysis.
 */
function announcementReviewFactoryForHistory(): AnnouncementReviewFactory
{
    /** @var AnnouncementReviewFactory $factory */
    $factory = AnnouncementReview::factory();

    return $factory;
}

/**
 * Create a review history row for the given announcement.
 */
function createAnnouncementReviewEntry(
    Announcement $announcement,
    AnnouncementReviewAction $announcementReviewAction,
    ?User $user,
    string $createdAt,
    ?string $comment = null,
): AnnouncementReview {
    return announcementReviewFactoryForHistory()
        ->for($announcement)
        ->createOne([
            'action' => $announcementReviewAction,
            'comment' => $comment,
            'reviewed_by' => $user?->id,
            'created_at' => $createdAt,
        ]);
}

it('lists only this announcement review history in chronological order with comments and actors', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    $announcement = announcementFactoryForReviewHistory()->createOne();
    $user = createUser();
    $announcementReview = createAnnouncementReviewEntry(
        $announcement,
        AnnouncementReviewAction::Submitted,
        $user,
        '2026-10-01 10:00:00',
        'Initial submission comment',
    );
    $approvedReview = createAnnouncementReviewEntry(
        $announcement,
        AnnouncementReviewAction::Approved,
        $user,
        '2026-10-03 10:00:00',
        'Approval comment',
    );
    createAnnouncementReviewEntry(
        announcementFactoryForReviewHistory()->createOne(),
        AnnouncementReviewAction::Rejected,
        $user,
        '2026-10-04 10:00:00',
        'Another announcement comment',
    );

    $testable = announcementReviewsRelationManagerFor($announcement)
        ->assertSee('Initial submission comment')
        ->assertSee('Approval comment')
        ->assertSee($user->name)
        ->assertSee(TranslatedText::of('admin/announcements.review_actions.submitted'))
        ->assertSee(TranslatedText::of('admin/announcements.review_actions.approved'))
        ->assertDontSee('Another announcement comment');
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, AnnouncementReview::class))
        ->toBe([
            FilamentTableRecords::recordKey($announcementReview),
            FilamentTableRecords::recordKey($approvedReview),
        ]);
});

it('shows a deleted reviewer as a deleted user', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    $announcement = announcementFactoryForReviewHistory()->createOne();
    createAnnouncementReviewEntry(
        $announcement,
        AnnouncementReviewAction::Rejected,
        null,
        '2026-10-01 10:00:00',
        'The announcement needs changes.',
    );

    announcementReviewsRelationManagerFor($announcement)
        ->assertOk()
        ->assertSee('The announcement needs changes.')
        ->assertSee(TranslatedText::of('admin/common.deleted_user'));
});

it('offers no create, edit or delete actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    $announcement = announcementFactoryForReviewHistory()->createOne();
    createAnnouncementReviewEntry(
        $announcement,
        AnnouncementReviewAction::Submitted,
        createUser(),
        '2026-10-01 10:00:00',
    );

    $table = FilamentTableRecords::table(announcementReviewsRelationManagerFor($announcement))->getTable();

    expect($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});

it('registers the review history for authorized announcement details only', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
    $announcement = announcementFactoryForReviewHistory()->createOne();

    expect(AnnouncementResource::getRelations())
        ->toContain(AnnouncementReviewsRelationManager::class)
        ->and(AnnouncementReviewsRelationManager::canViewForRecord($announcement, ViewAnnouncement::class))
        ->toBeTrue()
        ->and(AnnouncementReviewsRelationManager::canViewForRecord($announcement, EditAnnouncement::class))
        ->toBeFalse();
});
