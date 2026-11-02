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
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

/**
 * Attach an image to the announcement on the same disk as the edit form.
 */
function attachEditAnnouncementImage(Announcement $announcement, string $fileName): void
{
    $announcement->addMedia(UploadedFile::fake()->image($fileName))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);
}

/**
 * Record the existing submission history for an in-review edit scenario.
 */
function addEditAnnouncementSubmission(Announcement $announcement): AnnouncementReview
{
    return $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Submitted,
        'reviewed_by' => $announcement->created_by,
    ]);
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

    it('resubmits an approved announcement when an image is added', function (): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->createOne();
        $administratorId = auth('admin')->id();

        editAnnouncementPage($announcement)
            ->set('data.image', [UploadedFile::fake()->image('added-banner.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        $announcementReview = $announcement->reviews()->firstOrFail();
        $updatedActivities = editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
            ->and($announcement->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->mime_type)->toBe('image/jpeg')
            ->and($announcement->reviews()->count())->toBe(1)
            ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Resubmitted)
            ->and($announcementReview->reviewed_by)->toBe($administratorId)
            ->and($updatedActivities)->toHaveCount(1)
            ->and($updatedActivities[0]->properties?->get('changed_attributes'))->toBe(['image'])
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
    });

    it('resubmits an approved announcement when its image is replaced', function (): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->createOne();
        attachEditAnnouncementImage($announcement, 'original-banner.jpg');
        $originalMediaUuid = $announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->uuid;

        editAnnouncementPage($announcement)
            ->set('data.image', [UploadedFile::fake()->image('replacement-banner.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
            ->and($announcement->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->uuid)->not->toBe($originalMediaUuid)
            ->and($announcement->reviews()->where('action', AnnouncementReviewAction::Resubmitted)->count())->toBe(1)
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
    });

    it('resubmits an approved announcement when its image is removed', function (): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->createOne();
        attachEditAnnouncementImage($announcement, 'removed-banner.jpg');

        editAnnouncementPage($announcement)
            ->set('data.image', [])
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
            ->and($announcement->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toBeEmpty()
            ->and($announcement->reviews()->where('action', AnnouncementReviewAction::Resubmitted)->count())->toBe(1)
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
    });

    it('does not resubmit an approved announcement when its image is unchanged', function (): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->createOne();
        attachEditAnnouncementImage($announcement, 'unchanged-banner.jpg');
        $originalMediaUuid = $announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->uuid;

        editAnnouncementPage($announcement)
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Approved)
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->uuid)->toBe($originalMediaUuid)
            ->and($announcement->reviews()->count())->toBe(0)
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty()
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toBeEmpty();
    });

    it('resubmits an in-review announcement when its image changes', function (): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->withStatus(AnnouncementStatus::InReview)->createOne();
        $previousSubmission = addEditAnnouncementSubmission($announcement);
        $administratorId = auth('admin')->id();

        editAnnouncementPage($announcement)
            ->set('data.image', [UploadedFile::fake()->image('in-review-banner.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        $latestSubmission = $announcement->reviews()
            ->reorder()
            ->whereIn('action', [AnnouncementReviewAction::Submitted->value, AnnouncementReviewAction::Resubmitted->value])
            ->orderByDesc('id')
            ->firstOrFail();
        $updatedActivities = editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
            ->and($announcement->reviews()->count())->toBe(2)
            ->and($previousSubmission->refresh()->action)->toBe(AnnouncementReviewAction::Submitted)
            ->and($latestSubmission->action)->toBe(AnnouncementReviewAction::Resubmitted)
            ->and($latestSubmission->reviewed_by)->toBe($administratorId)
            ->and($updatedActivities)->toHaveCount(1)
            ->and($updatedActivities[0]->properties?->get('changed_attributes'))->toBe(['image'])
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
    });

    it('records only one resubmission when content and image change together', function (AnnouncementStatus $startingStatus): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->withStatus($startingStatus)->createOne();

        if ($startingStatus === AnnouncementStatus::InReview) {
            addEditAnnouncementSubmission($announcement);
        }

        editAnnouncementPage($announcement)
            ->set('data.title', 'Título actualizado')
            ->set('data.image', [UploadedFile::fake()->image('combined-banner.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
            ->and($announcement->title)->toBe('Título actualizado')
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->mime_type)->toBe('image/jpeg')
            ->and($announcement->reviews()->where('action', AnnouncementReviewAction::Resubmitted)->count())->toBe(1)
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
    })->with([
        'approved' => AnnouncementStatus::Approved,
        'in review' => AnnouncementStatus::InReview,
    ]);

    it('keeps draft and rejected statuses when their images change', function (AnnouncementStatus $announcementStatus): void {
        Storage::fake('public');
        $announcement = editAnnouncementFactory()->withStatus($announcementStatus)->createOne();

        editAnnouncementPage($announcement)
            ->set('data.image', [UploadedFile::fake()->image('unapproved-banner.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->status)->toBe($announcementStatus)
            ->and($announcement->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->mime_type)->toBe('image/jpeg')
            ->and($announcement->reviews()->count())->toBe(0)
            ->and(editAnnouncementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toBeEmpty();
    })->with([
        'draft' => AnnouncementStatus::Draft,
        'rejected' => AnnouncementStatus::Rejected,
    ]);

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

    it('keeps the saved presentation when the type changes while editing', function (): void {
        $announcement = editAnnouncementFactory()->asDialog()->ofType(AnnouncementType::Critical)->createOne();

        editAnnouncementPage($announcement)
            ->assertSet('data.display', AnnouncementDisplay::Dialog->value)
            ->set('data.type', AnnouncementType::Info->value)
            ->assertSet('data.display', AnnouncementDisplay::Dialog->value)
            ->call('save')
            ->assertHasNoErrors();

        expect($announcement->refresh()->display)->toBe(AnnouncementDisplay::Dialog)
            ->and($announcement->type)->toBe(AnnouncementType::Info);
    });

    it('does not count the announcement being edited as an overlapping dialog', function (): void {
        $announcement = editAnnouncementFactory()->asDialog()->createOne(['ends_at' => null]);

        editAnnouncementPage($announcement)->assertDontSee('other dialog');
    });
});
