<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\User\Models\User;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The announcement factory, typed for static analysis.
 */
function reviewActionsAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * An announcement in review with a submission attributed to its latest sender.
 */
function announcementUnderReviewForActionTest(User $author, User $submitter): Announcement
{
    $announcement = reviewActionsAnnouncementFactory()
        ->withStatus(AnnouncementStatus::InReview)
        ->createdBy($author)
        ->createOne();

    $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Submitted,
        'reviewed_by' => $submitter->id,
    ]);

    return $announcement;
}

/**
 * Mount a review action from an announcement row.
 *
 * @return Testable<ListAnnouncements>
 */
function mountAnnouncementReviewTableAction(string $actionName, Announcement $announcement): Testable
{
    return Livewire::test(ListAnnouncements::class)
        ->set('activeTab', 'all')
        ->call('loadTable')
        ->call('mountAction', $actionName, [], [
            'table' => true,
            'recordKey' => FilamentTableRecords::recordKey($announcement),
        ]);
}

/**
 * Mount a review action from the announcement detail page.
 *
 * @return Testable<ViewAnnouncement>
 */
function mountAnnouncementReviewDetailAction(string $actionName, Announcement $announcement): Testable
{
    return Livewire::test(ViewAnnouncement::class, ['record' => $announcement->getKey()])
        ->call('mountAction', $actionName);
}

/**
 * Whether a Livewire page has a mounted action.
 *
 * @param  Testable<ListAnnouncements>|Testable<ViewAnnouncement>  $testable
 */
function announcementReviewActionIsMounted(Testable $testable): bool
{
    return $testable->instance()->mountedActions !== [];
}

/**
 * Get the announcement record displayed by a review page.
 *
 * @param  Testable<ViewAnnouncement>  $testable
 */
function announcementReviewActionRecord(Testable $testable): Announcement
{
    $model = $testable->instance()->getRecord();

    throw_unless($model instanceof Announcement, LogicException::class, 'Expected the review page to contain an announcement.');

    return $model;
}

/**
 * Count audit rows for an announcement workflow event.
 */
function announcementReviewActionEventCount(ActivityLogAdminEvent $activityLogAdminEvent): int
{
    return ActivityLog::query()
        ->where('event', $activityLogAdminEvent->value)
        ->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('submits a draft from the table and records the submission', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsManager,
    );
    $announcement = reviewActionsAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();

    mountAnnouncementReviewTableAction('submitForReview', $announcement)
        ->call('callMountedAction')
        ->assertHasNoErrors();

    Notification::assertNotified('Announcement needs review');

    $announcementReview = $announcement->reviews()->sole();

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcementReview->action)->toBe(AnnouncementReviewAction::Submitted)
        ->and($announcementReview->reviewed_by)->toBe($user->id)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementSubmitted))->toBe(1);
});

it('resubmits a rejected announcement from its detail page and preserves its history', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsManager,
    );
    $previousReviewer = createUser();
    $announcement = reviewActionsAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Rejected)
        ->createdBy($user)
        ->createOne();
    $announcementReview = $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Rejected,
        'comment' => 'Please add more detail.',
        'reviewed_by' => $previousReviewer->id,
    ]);

    $testable = mountAnnouncementReviewDetailAction('submitForReview', $announcement);

    $testable
        ->call('callMountedAction')
        ->assertHasNoErrors();

    Notification::assertNotified('Announcement needs review');

    $resubmission = $announcement->reviews()
        ->where('action', AnnouncementReviewAction::Resubmitted->value)
        ->sole();

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(2)
        ->and($announcementReview->refresh()->action)->toBe(AnnouncementReviewAction::Rejected)
        ->and($announcementReview->comment)->toBe('Please add more detail.')
        ->and($resubmission->action)->toBe(AnnouncementReviewAction::Resubmitted)
        ->and($resubmission->reviewed_by)->toBe($user->id)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementSubmitted))->toBe(1)
        ->and(announcementReviewActionRecord($testable)->status)
        ->toBe(AnnouncementStatus::InReview);
});

it('approves the latest submission from the table with an optional comment', function (?string $comment): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());
    $testable = mountAnnouncementReviewTableAction('approve', $announcement);

    if ($comment !== null) {
        $testable->set('mountedActions.0.data.comment', $comment);
    }

    $testable->call('callMountedAction')->assertHasNoErrors();

    Notification::assertNotified('Announcement approved');

    $announcementReview = $announcement->reviews()->where('action', AnnouncementReviewAction::Approved->value)->sole();

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Approved)
        ->and($announcementReview->reviewed_by)->toBe($user->id)
        ->and($announcementReview->comment)->toBe($comment)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(1);
})->with([
    'without a comment' => [null],
    'with a comment' => ['Approved after reviewing the updated copy.'],
]);

it('rejects the latest submission from its detail page with a comment', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());

    $testable = mountAnnouncementReviewDetailAction('reject', $announcement);

    $testable
        ->set('mountedActions.0.data.comment', 'The announcement needs a clearer call to action.')
        ->call('callMountedAction')
        ->assertHasNoErrors();

    Notification::assertNotified('Announcement rejected');

    $announcementReview = $announcement->reviews()->where('action', AnnouncementReviewAction::Rejected->value)->sole();

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Rejected)
        ->and($announcementReview->reviewed_by)->toBe($user->id)
        ->and($announcementReview->comment)->toBe('The announcement needs a clearer call to action.')
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(1)
        ->and(announcementReviewActionRecord($testable)->status)
        ->toBe(AnnouncementStatus::Rejected);
});

it('shows the approved status immediately on the detail page', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());
    $testable = mountAnnouncementReviewDetailAction('approve', $announcement);

    $testable
        ->call('callMountedAction')
        ->assertHasNoErrors();

    expect(announcementReviewActionRecord($testable)->status)->toBe(AnnouncementStatus::Approved);
});

it('does not mount review actions for an administrator without review permission', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsManager,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());

    $tableAction = mountAnnouncementReviewTableAction('approve', $announcement);
    $detailAction = mountAnnouncementReviewDetailAction('reject', $announcement);

    expect(announcementReviewActionIsMounted($tableAction))->toBeFalse()
        ->and(announcementReviewActionIsMounted($detailAction))->toBeFalse()
        ->and($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(1)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(0)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(0);
});

it('does not let the announcement author review it from the table or detail page', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest($user, createUser());

    $tableAction = mountAnnouncementReviewTableAction('approve', $announcement);
    $detailAction = mountAnnouncementReviewDetailAction('reject', $announcement);

    expect(announcementReviewActionIsMounted($tableAction))->toBeFalse()
        ->and(announcementReviewActionIsMounted($detailAction))->toBeFalse()
        ->and($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(1)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(0)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(0);
});

it('does not let the latest submitter review their own submission', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), $user);

    $tableAction = mountAnnouncementReviewTableAction('approve', $announcement);
    $detailAction = mountAnnouncementReviewDetailAction('reject', $announcement);

    expect(announcementReviewActionIsMounted($tableAction))->toBeFalse()
        ->and(announcementReviewActionIsMounted($detailAction))->toBeFalse()
        ->and($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(1)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(0)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(0);
});

it('does not offer review actions when the announcement is outside review', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = reviewActionsAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Approved)
        ->createOne();

    $testable = mountAnnouncementReviewTableAction('approve', $announcement);

    expect(announcementReviewActionIsMounted($testable))->toBeFalse()
        ->and($announcement->refresh()->status)->toBe(AnnouncementStatus::Approved)
        ->and($announcement->reviews()->count())->toBe(0)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(0);
});

it('requires a comment before a reviewer can reject an announcement', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());

    mountAnnouncementReviewDetailAction('reject', $announcement)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.comment' => 'required']);

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(1)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(0);
});

it('rejects a comment longer than the review limit without changing the announcement', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());

    mountAnnouncementReviewDetailAction('reject', $announcement)
        ->set('mountedActions.0.data.comment', str_repeat('a', 1001))
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.comment' => 'max']);

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(1)
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementRejected))->toBe(0);
});

it('ignores an approval when the latest submission changes after the action is mounted', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $testCase,
        AdminRole::Admin,
        AdminRole::AdminAnnouncementsReviewer,
    );
    $announcement = announcementUnderReviewForActionTest(createUser(), createUser());
    $announcementReview = $announcement->reviews()->sole();
    $testable = mountAnnouncementReviewTableAction('approve', $announcement);
    $expectedSubmissionReviewId = $testable->get('mountedActions.0.data.expected_submission_review_id');

    $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Resubmitted,
        'reviewed_by' => createUser()->id,
    ]);

    $testable->call('callMountedAction')->assertHasNoErrors();

    expect($expectedSubmissionReviewId)->toBe($announcementReview->id)
        ->and($announcement->refresh()->status)->toBe(AnnouncementStatus::InReview)
        ->and($announcement->reviews()->count())->toBe(2)
        ->and($announcement->reviews()->where('action', AnnouncementReviewAction::Approved->value)->exists())->toBeFalse()
        ->and(announcementReviewActionEventCount(ActivityLogAdminEvent::AnnouncementApproved))->toBe(0);
});
