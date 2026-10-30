<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Support\Translation\TranslatedText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function announcementWorkflowFieldsFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * The edit page for a workflow fields test announcement.
 *
 * @return Testable<EditAnnouncement>
 */
function editAnnouncementWorkflowFieldsPage(Announcement $announcement): Testable
{
    return Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()]);
}

/**
 * The detail page for a workflow fields test announcement.
 *
 * @return Testable<ViewAnnouncement>
 */
function viewAnnouncementWorkflowFieldsPage(Announcement $announcement): Testable
{
    return Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getKey()]);
}

/**
 * The translated copy used by the approved-content re-review notice.
 *
 * @return array{heading: string, description: string}
 */
function announcementWorkflowReapprovalNoticeTexts(): array
{
    return [
        'heading' => TranslatedText::of('admin/announcements.form.reapproval_notice_heading'),
        'description' => TranslatedText::of('admin/announcements.form.reapproval_notice_description'),
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminAnnouncementsManager);
});

it('shows the current workflow status on the announcement detail page', function (
    string $locale,
    AnnouncementStatus $announcementStatus,
    string $expectedStatusLabel,
): void {
    $originalLocale = app()->getLocale();

    try {
        app()->setLocale($locale);
        $announcement = announcementWorkflowFieldsFactory()
            ->withStatus($announcementStatus)
            ->createOne();

        viewAnnouncementWorkflowFieldsPage($announcement)
            ->assertOk()
            ->assertSeeText($expectedStatusLabel);
    } finally {
        app()->setLocale($originalLocale);
    }
})->with([
    'English draft' => ['en', AnnouncementStatus::Draft, 'Draft'],
    'English in review' => ['en', AnnouncementStatus::InReview, 'In review'],
    'English approved' => ['en', AnnouncementStatus::Approved, 'Approved'],
    'English rejected' => ['en', AnnouncementStatus::Rejected, 'Rejected'],
    'Spanish draft' => ['es', AnnouncementStatus::Draft, 'Borrador'],
    'Spanish in review' => ['es', AnnouncementStatus::InReview, 'En revisión'],
    'Spanish approved' => ['es', AnnouncementStatus::Approved, 'Aprobado'],
    'Spanish rejected' => ['es', AnnouncementStatus::Rejected, 'Rechazado'],
]);

it('warns before saving a content change to an approved announcement and allows the resubmission', function (): void {
    $announcement = announcementWorkflowFieldsFactory()->createOne();
    $noticeTexts = announcementWorkflowReapprovalNoticeTexts();
    $testable = editAnnouncementWorkflowFieldsPage($announcement)
        ->assertOk()
        ->assertDontSee($noticeTexts['heading'])
        ->assertDontSee($noticeTexts['description'])
        ->set('data.title', 'Updated announcement title')
        ->assertSee($noticeTexts['heading'])
        ->assertSee($noticeTexts['description']);

    $testable->call('save')->assertHasNoErrors();

    $announcementReview = $announcement->reviews()
        ->where('action', AnnouncementReviewAction::Resubmitted->value)
        ->sole();

    expect($announcement->refresh()->title)->toBe('Updated announcement title')
        ->and($announcement->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcementReview->reviewed_by)->toBe(auth('admin')->id());
});

it('does not warn or resubmit when only the schedule changes', function (): void {
    $announcement = announcementWorkflowFieldsFactory()->createOne();
    $noticeTexts = announcementWorkflowReapprovalNoticeTexts();
    $startsAt = now()->addDays(2)->toDateTimeString();
    $endsAt = now()->addDays(4)->toDateTimeString();

    editAnnouncementWorkflowFieldsPage($announcement)
        ->set('data.starts_at', $startsAt)
        ->set('data.ends_at', $endsAt)
        ->assertDontSee($noticeTexts['heading'])
        ->assertDontSee($noticeTexts['description'])
        ->call('save')
        ->assertHasNoErrors();

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Approved)
        ->and($announcement->starts_at->toDateTimeString())->toBe($startsAt)
        ->and($announcement->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and($announcement->reviews()->count())->toBe(0);
});

it('warns before changing the image of an approved announcement', function (): void {
    Storage::fake('public');
    $announcement = announcementWorkflowFieldsFactory()->createOne();
    $noticeTexts = announcementWorkflowReapprovalNoticeTexts();

    editAnnouncementWorkflowFieldsPage($announcement)
        ->set('data.image', [UploadedFile::fake()->image('updated-banner.jpg')])
        ->assertSee($noticeTexts['heading'])
        ->assertSee($noticeTexts['description']);
});
