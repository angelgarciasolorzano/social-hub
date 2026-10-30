<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Announcement\Data\AnnouncementData;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\Modules\Announcement\Services\AnnouncementService;
use App\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

const ANNOUNCEMENT_TITLE_SENTINEL = 'centinela-titulo-de-anuncio';
const ANNOUNCEMENT_BODY_SENTINEL = 'centinela-cuerpo-de-anuncio';
const ANNOUNCEMENT_REVIEW_COMMENT_SENTINEL = 'centinela-comentario-de-revision';

/**
 * Resolve the service from the container, as the admin panel does.
 */
function announcementService(): AnnouncementService
{
    return resolve(AnnouncementService::class);
}

/**
 * The announcement factory, typed for static analysis.
 */
function serviceAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * Announcement data as the admin form would submit it.
 */
function announcementDataForService(
    string $title = ANNOUNCEMENT_TITLE_SENTINEL,
    AnnouncementDisplay $announcementDisplay = AnnouncementDisplay::Toast,
): AnnouncementData {
    return new AnnouncementData(
        title: $title,
        body: '<p>'.ANNOUNCEMENT_BODY_SENTINEL.'</p>',
        type: AnnouncementType::Warning,
        audience: AnnouncementAudience::Authenticated,
        startsAt: now()->subHour()->toImmutable(),
        endsAt: now()->addDay()->toImmutable(),
        isDismissible: true,
        buttonLabel: null,
        buttonUrl: null,
        display: $announcementDisplay,
    );
}

/**
 * Activity log rows recorded for the given admin event.
 *
 * @return array<int, ActivityLog>
 */
function announcementActivities(ActivityLogAdminEvent $activityLogAdminEvent): array
{
    return ActivityLog::query()->where('event', $activityLogAdminEvent->value)->get()->all();
}

/**
 * Submit a draft announcement and return its submission history entry.
 *
 * @return array{announcement: Announcement, submissionReview: AnnouncementReview}
 */
function submitAnnouncementForService(User $author, ?User $submitter = null): array
{
    $announcement = serviceAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($author)
        ->createOne();
    $submittedAnnouncement = announcementService()->submitForReview($announcement, $submitter ?? $author);
    $announcementReview = $submittedAnnouncement->reviews()->firstOrFail();

    return [
        'announcement' => $submittedAnnouncement,
        'submissionReview' => $announcementReview,
    ];
}

/**
 * Return the latest submission history entry for a service test.
 */
function latestSubmissionReviewForService(Announcement $announcement): AnnouncementReview
{
    /** @var AnnouncementReview $submissionReview */
    $submissionReview = $announcement->reviews()
        ->reorder()
        ->whereIn('action', [AnnouncementReviewAction::Submitted->value, AnnouncementReviewAction::Resubmitted->value])
        ->latest()
        ->orderByDesc('id')
        ->firstOrFail();

    return $submissionReview;
}

/**
 * Approve a test announcement with a valid submission and reviewer history.
 */
function approvedAnnouncementForService(User $author, User $reviewer, AnnouncementData $announcementData): Announcement
{
    $announcement = announcementService()->create($announcementData, $author);
    $submittedAnnouncement = announcementService()->submitForReview($announcement, $author);
    $announcementReview = latestSubmissionReviewForService($submittedAnnouncement);

    return announcementService()->approve($submittedAnnouncement, $reviewer, $announcementReview->id);
}

/**
 * Change one content field while preserving the current schedule and other values.
 */
function announcementDataWithContentChangeForService(
    AnnouncementData $announcementData,
    string $contentAttribute,
): AnnouncementData {
    $updatedAnnouncementData = clone $announcementData;

    switch ($contentAttribute) {
        case 'title':
            $updatedAnnouncementData->title = 'Título actualizado';

            break;
        case 'body':
            $updatedAnnouncementData->body = '<p>Contenido actualizado.</p>';

            break;
        case 'type':
            $updatedAnnouncementData->type = AnnouncementType::Critical;

            break;
        case 'audience':
            $updatedAnnouncementData->audience = AnnouncementAudience::All;

            break;
        case 'display':
            $updatedAnnouncementData->display = AnnouncementDisplay::Dialog;

            break;
        case 'dismissibility':
            $updatedAnnouncementData->isDismissible = false;

            break;
        case 'button':
            $updatedAnnouncementData->buttonLabel = 'Más información';
            $updatedAnnouncementData->buttonUrl = 'https://example.com/announcement';

            break;
        default:
            throw new LogicException("Unknown announcement content field {$contentAttribute}.");
    }

    return $updatedAnnouncementData;
}

it('creates an announcement authored by the administrator and audits it', function (): void {
    $user = createUser();

    $announcement = announcementService()->create(announcementDataForService(), $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementCreated);

    expect($announcement->created_by)->toBe($user->id)
        ->and($announcement->type)->toBe(AnnouncementType::Warning)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($announcement))->toBeTrue()
        ->and($activities[0]->causer?->is($user))->toBeTrue();
});

it('records only the names of the changed attributes when updating', function (): void {
    $user = createUser();
    $announcement = announcementService()->create(announcementDataForService(), $user);

    announcementService()->update($announcement, announcementDataForService('Otro título'), $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

    expect($announcement->refresh()->title)->toBe('Otro título')
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->properties?->get('changed_attributes'))->toBe(['title']);
});

it('submits a draft for review and records the submitting administrator', function (): void {
    $user = createUser();
    $announcement = serviceAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();

    $submittedAnnouncement = announcementService()->submitForReview($announcement, $user);
    $announcementReview = $submittedAnnouncement->reviews()->firstOrFail();
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted);

    expect($submittedAnnouncement->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Submitted)
        ->and($announcementReview->reviewed_by)->toBe($user->id)
        ->and($announcementReview->comment)->toBeNull()
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($submittedAnnouncement))->toBeTrue()
        ->and($activities[0]->causer?->is($user))->toBeTrue();
});

it('resubmits a rejected announcement with a new submitter history entry', function (): void {
    $user = createUser();
    $announcement = serviceAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Rejected)
        ->createdBy($user)
        ->createOne();

    $resubmittedAnnouncement = announcementService()->submitForReview($announcement, $user);
    $announcementReview = $resubmittedAnnouncement->reviews()->firstOrFail();

    expect($resubmittedAnnouncement->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Resubmitted)
        ->and($announcementReview->reviewed_by)->toBe($user->id)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
});

it('refuses to submit an announcement outside a submittable state without writing history or audit', function (AnnouncementStatus $announcementStatus): void {
    $announcement = serviceAnnouncementFactory()->withStatus($announcementStatus)->createOne();

    expect(fn (): Announcement => announcementService()->submitForReview($announcement, createUser()))
        ->toThrow(LogicException::class)
        ->and($announcement->refresh()->status)
        ->toBe($announcementStatus)
        ->and($announcement->reviews()
            ->count())
        ->toBe(0)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))
        ->toBeEmpty();
})->with([
    'already in review' => AnnouncementStatus::InReview,
    'already approved' => AnnouncementStatus::Approved,
]);

it('approves the latest submission with an optional comment', function (?string $comment): void {
    $user = createUser();
    $reviewer = createUser();
    $submission = submitAnnouncementForService($user);

    $approvedAnnouncement = announcementService()->approve(
        $submission['announcement'],
        $reviewer,
        $submission['submissionReview']->id,
        $comment,
    );
    $announcementReview = $approvedAnnouncement->reviews()
        ->where('action', AnnouncementReviewAction::Approved->value)
        ->firstOrFail();
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementApproved);

    expect($approvedAnnouncement->status)->toBe(AnnouncementStatus::Approved)
        ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Approved)
        ->and($announcementReview->comment)->toBe($comment)
        ->and($announcementReview->reviewed_by)->toBe($reviewer->id)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($approvedAnnouncement))->toBeTrue()
        ->and($activities[0]->causer?->is($reviewer))->toBeTrue();
})->with([
    'without a comment' => null,
    'with a comment' => 'Aprobado con cambios menores.',
]);

it('refuses approval or rejection unless the announcement has a current submission in review', function (AnnouncementStatus $announcementStatus, string $decision): void {
    $announcement = serviceAnnouncementFactory()->withStatus($announcementStatus)->createOne();
    $user = createUser();

    $reviewAttempt = match ($decision) {
        'approve' => fn (): Announcement => announcementService()->approve($announcement, $user, 1),
        'reject' => fn (): Announcement => announcementService()->reject($announcement, $user, 1, 'El contenido requiere cambios.'),
        default => throw new InvalidArgumentException("Unknown review decision {$decision}."),
    };

    expect($reviewAttempt)->toThrow(LogicException::class)
        ->and($announcement->refresh()->status)
        ->toBe($announcementStatus)
        ->and($announcement->reviews()
            ->count())
        ->toBe(0)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementApproved))
        ->toBeEmpty()
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementRejected))
        ->toBeEmpty();
})->with([
    'cannot approve a draft' => [AnnouncementStatus::Draft, 'approve'],
    'cannot reject a draft' => [AnnouncementStatus::Draft, 'reject'],
    'cannot approve a rejected announcement' => [AnnouncementStatus::Rejected, 'approve'],
    'cannot reject an approved announcement' => [AnnouncementStatus::Approved, 'reject'],
    'cannot decide an in-review state without a submission record' => [AnnouncementStatus::InReview, 'approve'],
]);

it('rejects with a 1000-character comment and keeps its content in review history only', function (): void {
    $user = createUser();
    $reviewer = createUser();
    $comment = str_repeat('ñ', 1000);
    $announcement = serviceAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne([
            'title' => ANNOUNCEMENT_TITLE_SENTINEL,
            'body' => ANNOUNCEMENT_BODY_SENTINEL,
        ]);
    $submittedAnnouncement = announcementService()->submitForReview($announcement, $user);
    $announcementReview = $submittedAnnouncement->reviews()->firstOrFail();

    $rejectedAnnouncement = announcementService()->reject(
        $submittedAnnouncement,
        $reviewer,
        $announcementReview->id,
        $comment,
    );
    $rejectionReview = $rejectedAnnouncement->reviews()
        ->where('action', AnnouncementReviewAction::Rejected->value)
        ->firstOrFail();
    $activities = ActivityLog::query()->get()->toJson();

    expect($rejectedAnnouncement->status)->toBe(AnnouncementStatus::Rejected)
        ->and($rejectionReview->comment)->toBe($comment)
        ->and($rejectionReview->reviewed_by)->toBe($reviewer->id)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementRejected))->toHaveCount(1)
        ->and($activities)->not->toContain(ANNOUNCEMENT_TITLE_SENTINEL)
        ->and($activities)->not->toContain(ANNOUNCEMENT_BODY_SENTINEL)
        ->and($activities)->not->toContain($comment);
});

it('refuses a rejection comment that is empty or longer than 1000 characters without writing', function (string $comment): void {
    $user = createUser();
    $reviewer = createUser();
    $submission = submitAnnouncementForService($user);

    expect(fn (): Announcement => announcementService()->reject(
        $submission['announcement'],
        $reviewer,
        $submission['submissionReview']->id,
        $comment,
    ))->toThrow(InvalidArgumentException::class)
        ->and($submission['announcement']->refresh()->status)
        ->toBe(AnnouncementStatus::InReview)
        ->and($submission['announcement']->reviews()
            ->count())
        ->toBe(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementRejected))
        ->toBeEmpty();
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'over 1000 characters' => str_repeat('a', 1001),
]);

it('prevents the announcement author from approving their own submission', function (): void {
    $user = createUser();
    $submitter = createUser();
    $submission = submitAnnouncementForService($user, $submitter);

    expect(fn (): Announcement => announcementService()->approve(
        $submission['announcement'],
        $user,
        $submission['submissionReview']->id,
    ))->toThrow(LogicException::class)
        ->and($submission['announcement']->refresh()->status)
        ->toBe(AnnouncementStatus::InReview)
        ->and($submission['announcement']->reviews()
            ->count())
        ->toBe(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementApproved))
        ->toBeEmpty();
});

it('prevents the latest submitter from rejecting their own submission', function (): void {
    $user = createUser();
    $submitter = createUser();
    $submission = submitAnnouncementForService($user, $submitter);

    expect(fn (): Announcement => announcementService()->reject(
        $submission['announcement'],
        $submitter,
        $submission['submissionReview']->id,
        'El contenido requiere cambios.',
    ))->toThrow(LogicException::class)
        ->and($submission['announcement']->refresh()->status)
        ->toBe(AnnouncementStatus::InReview)
        ->and($submission['announcement']->reviews()
            ->count())
        ->toBe(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementRejected))
        ->toBeEmpty();
});

it('rejects a stale review decision after the announcement has been resubmitted', function (): void {
    $user = createUser();
    $editor = createUser();
    $reviewer = createUser();
    $submission = submitAnnouncementForService($user);

    $rejectedAnnouncement = announcementService()->reject(
        $submission['announcement'],
        $reviewer,
        $submission['submissionReview']->id,
        'El contenido requiere cambios.',
    );
    $resubmittedAnnouncement = announcementService()->submitForReview($rejectedAnnouncement, $editor);
    $announcementReview = latestSubmissionReviewForService($resubmittedAnnouncement);

    expect($announcementReview->id)->not->toBe($submission['submissionReview']->id)
        ->and(fn (): Announcement => announcementService()->approve(
            $resubmittedAnnouncement,
            $reviewer,
            $submission['submissionReview']->id,
        ))
        ->toThrow(LogicException::class)
        ->and($resubmittedAnnouncement->refresh()->status)
        ->toBe(AnnouncementStatus::InReview)
        ->and($resubmittedAnnouncement->reviews()
            ->count())
        ->toBe(3)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementApproved))
        ->toBeEmpty();
});

it('rolls back a submission when its activity log cannot be written', function (): void {
    $user = createUser();
    $announcement = serviceAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();

    ActivityLog::creating(function (ActivityLog $activityLog): never {
        throw new RuntimeException('Activity log unavailable.');
    });

    expect(fn (): Announcement => announcementService()->submitForReview($announcement, $user))
        ->toThrow(RuntimeException::class, 'Activity log unavailable.')
        ->and($announcement->refresh()->status)
        ->toBe(AnnouncementStatus::Draft)
        ->and($announcement->reviews()
            ->count())
        ->toBe(0)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))
        ->toBeEmpty();
});

it('records the display as the only changed attribute when only the presentation changes', function (): void {
    $this->freezeSecond();
    $user = createUser();
    $announcement = announcementService()->create(announcementDataForService(), $user);

    announcementService()->update($announcement, announcementDataForService(announcementDisplay: AnnouncementDisplay::Dialog), $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated);

    expect($announcement->refresh()->display)->toBe(AnnouncementDisplay::Dialog)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->properties?->get('changed_attributes'))->toBe(['display']);
});

it('resubmits an approved announcement when its content changes', function (string $contentAttribute): void {
    $user = createUser();
    $reviewer = createUser();
    $editor = createUser();
    $announcementData = announcementDataForService();
    $announcement = approvedAnnouncementForService($user, $reviewer, $announcementData);

    $updatedAnnouncement = announcementService()->update(
        $announcement,
        announcementDataWithContentChangeForService($announcementData, $contentAttribute),
        $editor,
    );
    $announcementReview = latestSubmissionReviewForService($updatedAnnouncement);

    expect($updatedAnnouncement->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Resubmitted)
        ->and($announcementReview->reviewed_by)->toBe($editor->id)
        ->and($updatedAnnouncement->reviews()->count())->toBe(3)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toHaveCount(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(2);
})->with([
    'title' => 'title',
    'body' => 'body',
    'button' => 'button',
    'type' => 'type',
    'audience' => 'audience',
    'presentation' => 'display',
    'dismissibility' => 'dismissibility',
]);

it('keeps an approved announcement approved when only its dates change', function (): void {
    $this->freezeSecond();
    $user = createUser();
    $reviewer = createUser();
    $editor = createUser();
    $announcementData = announcementDataForService();
    $announcement = approvedAnnouncementForService($user, $reviewer, $announcementData);
    $updatedAnnouncementData = clone $announcementData;
    $updatedAnnouncementData->startsAt = now()->addHours(2)->toImmutable();
    $updatedAnnouncementData->endsAt = now()->addDays(2)->toImmutable();

    $updatedAnnouncement = announcementService()->update($announcement, $updatedAnnouncementData, $editor);

    expect($updatedAnnouncement->status)->toBe(AnnouncementStatus::Approved)
        ->and($updatedAnnouncement->reviews()->count())->toBe(2)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toHaveCount(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
});

it('uses the current locked state when an editor submits data from a stale in-review model', function (): void {
    $user = createUser();
    $reviewer = createUser();
    $editor = createUser();
    $announcementData = announcementDataForService();
    $announcement = announcementService()->create($announcementData, $user);
    $submittedAnnouncement = announcementService()->submitForReview($announcement, $user);
    $staleAnnouncement = Announcement::query()
        ->whereKey($announcement->getKey())
        ->firstOrFail();
    $announcementReview = latestSubmissionReviewForService($submittedAnnouncement);

    announcementService()->approve($submittedAnnouncement, $reviewer, $announcementReview->id);

    $updatedAnnouncement = announcementService()->update(
        $staleAnnouncement,
        announcementDataWithContentChangeForService($announcementData, 'title'),
        $editor,
    );
    $resubmissionReview = latestSubmissionReviewForService($updatedAnnouncement);

    expect($updatedAnnouncement->status)->toBe(AnnouncementStatus::InReview)
        ->and($resubmissionReview->action)->toBe(AnnouncementReviewAction::Resubmitted)
        ->and($resubmissionReview->reviewed_by)->toBe($editor->id)
        ->and($updatedAnnouncement->reviews()->count())->toBe(3)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementApproved))->toHaveCount(1)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(2);
});

it('rolls back an approved content change when the resubmission audit cannot be written', function (): void {
    $user = createUser();
    $reviewer = createUser();
    $editor = createUser();
    $announcementData = announcementDataForService();
    $announcement = approvedAnnouncementForService($user, $reviewer, $announcementData);

    ActivityLog::creating(function (ActivityLog $activityLog): void {
        throw_if($activityLog->event === ActivityLogAdminEvent::AnnouncementSubmitted->value, RuntimeException::class, 'Activity log unavailable.');
    });

    expect(fn (): Announcement => announcementService()->update(
        $announcement,
        announcementDataWithContentChangeForService($announcementData, 'title'),
        $editor,
    ))->toThrow(RuntimeException::class, 'Activity log unavailable.');

    $unchangedAnnouncement = $announcement->fresh();

    expect($unchangedAnnouncement?->status)->toBe(AnnouncementStatus::Approved)
        ->and($unchangedAnnouncement?->title)->toBe($announcementData->title)
        ->and($unchangedAnnouncement?->reviews()->count())->toBe(2)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty()
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
});

it('keeps the presentation when duplicating a dialog', function (): void {
    $announcement = serviceAnnouncementFactory()->asDialog()->createOne();

    $copy = announcementService()->duplicate($announcement, createUser());

    expect($copy->refresh()->display)->toBe(AnnouncementDisplay::Dialog);
});

it('records nothing when an update changes nothing', function (): void {
    $this->freezeSecond();
    $user = createUser();
    $reviewer = createUser();
    $editor = createUser();
    $announcementData = announcementDataForService();
    $announcement = approvedAnnouncementForService($user, $reviewer, $announcementData);

    $updatedAnnouncement = announcementService()->update($announcement, $announcementData, $editor);

    expect($updatedAnnouncement->status)->toBe(AnnouncementStatus::Approved)
        ->and($updatedAnnouncement->reviews()->count())->toBe(2)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUpdated))->toBeEmpty()
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementSubmitted))->toHaveCount(1);
});

it('unpublishes a running announcement by ending it now', function (): void {
    $this->freezeSecond();
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->active()->createOne();

    announcementService()->unpublish($announcement, $user);

    expect($announcement->ends_at?->equalTo(now()))->toBeTrue()
        ->and(Announcement::query()->visibleTo(null)->exists())->toBeFalse()
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUnpublished))->toHaveCount(1);
});

it('refuses to unpublish an announcement that is not running, without writing anything', function (AnnouncementFactory $announcementFactory): void {
    $announcement = $announcementFactory->createOne();
    $endsAt = $announcement->ends_at?->toDateTimeString();

    expect(fn (): Announcement => announcementService()->unpublish($announcement, createUser()))
        ->toThrow(LogicException::class)
        ->and($announcement->refresh()->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUnpublished))->toBeEmpty();
})->with([
    'expired' => fn (): AnnouncementFactory => serviceAnnouncementFactory()->expired(),
    'scheduled' => fn (): AnnouncementFactory => serviceAnnouncementFactory()->scheduled(),
]);

it('refuses to unpublish announcements that are not approved', function (AnnouncementStatus $announcementStatus): void {
    $announcement = serviceAnnouncementFactory()
        ->active()
        ->withStatus($announcementStatus)
        ->createOne();
    $endsAt = $announcement->ends_at?->toDateTimeString();

    expect(fn (): Announcement => announcementService()->unpublish($announcement, createUser()))
        ->toThrow(LogicException::class, 'Only approved announcements may be unpublished.')
        ->and($announcement->refresh()->status)->toBe($announcementStatus)
        ->and($announcement->ends_at?->toDateTimeString())->toBe($endsAt)
        ->and(announcementActivities(ActivityLogAdminEvent::AnnouncementUnpublished))->toBeEmpty();
})->with([
    'draft' => AnnouncementStatus::Draft,
    'in review' => AnnouncementStatus::InReview,
    'rejected' => AnnouncementStatus::Rejected,
]);

it('duplicates an announcement with its image as an unreviewed copy scheduled for tomorrow', function (): void {
    Storage::fake('public');
    $this->freezeSecond();
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->withButton()->createOne();
    $announcement->addMedia(UploadedFile::fake()->image('banner.jpg'))
        ->toMediaCollection(Announcement::IMAGE_MEDIA_COLLECTION);

    $copy = announcementService()->duplicate($announcement, $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementDuplicated);

    expect($copy->id)->not->toBe($announcement->id)
        ->and($copy->status)->toBe(AnnouncementStatus::Draft)
        ->and($copy->title)->toBe($announcement->title)
        ->and($copy->button_url)->toBe($announcement->button_url)
        ->and($copy->starts_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($copy->ends_at)->toBeNull()
        ->and($copy->created_by)->toBe($user->id)
        ->and($copy->reviews()->count())->toBe(0)
        ->and($copy->getFirstMedia(Announcement::IMAGE_MEDIA_COLLECTION)?->file_name)->toBe('banner.jpg')
        ->and($announcement->refresh()->getMedia(Announcement::IMAGE_MEDIA_COLLECTION))->toHaveCount(1)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject?->is($copy))->toBeTrue()
        ->and($activities[0]->properties?->get('source_announcement_id'))->toBe($announcement->id);
});

it('deletes an announcement with its dismissals and keeps its id in the audit', function (): void {
    $user = createUser();
    $announcement = serviceAnnouncementFactory()->createOne();
    $announcement->dismissedBy()->attach(createUser(), ['created_at' => now()]);

    announcementService()->delete($announcement, $user);
    $activities = announcementActivities(ActivityLogAdminEvent::AnnouncementDeleted);

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeFalse()
        ->and($announcement->dismissedBy()->count())->toBe(0)
        ->and($activities)->toHaveCount(1)
        ->and($activities[0]->subject_id)->toBe($announcement->id)
        ->and($activities[0]->subject_type)->toBe(Announcement::MORPH_NAME);
});

it('never stores the title or body of an announcement in the activity log', function (): void {
    Storage::fake('public');
    $user = createUser();
    $announcement = announcementService()->create(announcementDataForService(), $user);
    announcementService()->update($announcement, announcementDataForService(ANNOUNCEMENT_TITLE_SENTINEL.'-editado'), $user);
    $copy = announcementService()->duplicate($announcement, $user);
    $announcement->forceFill(['status' => AnnouncementStatus::Approved])->save();
    announcementService()->unpublish($announcement, $user);
    announcementService()->delete($copy, $user);

    $storedActivities = ActivityLog::query()->get()->toJson();

    expect(ActivityLog::query()->where('log_name', 'admin')->count())->toBe(5)
        ->and($storedActivities)->not->toContain(ANNOUNCEMENT_TITLE_SENTINEL)
        ->and($storedActivities)->not->toContain(ANNOUNCEMENT_BODY_SENTINEL);
});
