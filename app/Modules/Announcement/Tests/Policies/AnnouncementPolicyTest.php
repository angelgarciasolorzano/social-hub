<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Policies\AnnouncementPolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * A user holding only the given Admin permission, through a role made for the test.
 */
function createUserWithAnnouncementPermission(AdminPermission $adminPermission): User
{
    $role = Role::findOrCreate('announcement-policy-test', 'admin');
    $role->givePermissionTo($adminPermission->value);

    $user = createUser();
    $user->assignRole($role);

    return $user;
}

/**
 * The announcement factory, typed for static analysis.
 */
function policyAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

/**
 * An announcement in review with a submission attributed to its latest sender.
 */
function inReviewPolicyAnnouncement(User $author, User $submitter): Announcement
{
    $announcement = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::InReview)
        ->createdBy($author)
        ->createOne();

    $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Submitted,
        'reviewed_by' => $submitter->id,
    ]);

    return $announcement;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('resolves AnnouncementPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(Announcement::class))->toBeInstanceOf(AnnouncementPolicy::class);
});

it('allows each panel ability only with its own permission', function (string $ability, AdminPermission $adminPermission): void {
    $announcement = policyAnnouncementFactory()->createOne();
    $allowedUser = createUserWithAnnouncementPermission($adminPermission);
    $otherPermission = $adminPermission === AdminPermission::AdminAnnouncementsView
        ? AdminPermission::AdminAnnouncementsViewAny
        : AdminPermission::AdminAnnouncementsView;
    $deniedUser = createUser();
    grantAdminPermissionThroughTestRole($deniedUser, $otherPermission);

    $arguments = in_array($ability, ['viewAny', 'create'], true) ? Announcement::class : $announcement;

    expect(Gate::forUser($allowedUser)->allows($ability, $arguments))->toBeTrue()
        ->and(Gate::forUser($deniedUser)->allows($ability, $arguments))->toBeFalse();
})->with([
    'viewAny' => ['viewAny', AdminPermission::AdminAnnouncementsViewAny],
    'view' => ['view', AdminPermission::AdminAnnouncementsView],
    'create' => ['create', AdminPermission::AdminAnnouncementsCreate],
    'update' => ['update', AdminPermission::AdminAnnouncementsUpdate],
    'replicate' => ['replicate', AdminPermission::AdminAnnouncementsCreate],
    'delete' => ['delete', AdminPermission::AdminAnnouncementsDelete],
]);

it('allows an authorized author to submit drafts and rejected announcements', function (AnnouncementStatus $announcementStatus): void {
    $user = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsUpdate);
    $announcement = policyAnnouncementFactory()
        ->withStatus($announcementStatus)
        ->createdBy($user)
        ->createOne();

    expect(Gate::forUser($user)->allows('submit', $announcement))->toBeTrue();
})->with([
    'draft' => AnnouncementStatus::Draft,
    'rejected' => AnnouncementStatus::Rejected,
]);

it('denies submitting when the author lacks the edit permission', function (): void {
    $user = createUser();
    $announcement = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();

    expect(Gate::forUser($user)->allows('submit', $announcement))->toBeFalse();
});

it('denies submitting when an editor is not the announcement author', function (): void {
    $user = createUser();
    $editor = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsUpdate);
    $announcement = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();

    expect(Gate::forUser($editor)->allows('submit', $announcement))->toBeFalse();
});

it('denies submitting announcements outside draft or rejected states', function (AnnouncementStatus $announcementStatus): void {
    $user = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsUpdate);
    $announcement = policyAnnouncementFactory()
        ->withStatus($announcementStatus)
        ->createdBy($user)
        ->createOne();

    expect(Gate::forUser($user)->allows('submit', $announcement))->toBeFalse();
})->with([
    'in review' => AnnouncementStatus::InReview,
    'approved' => AnnouncementStatus::Approved,
]);

it('allows reviewers with permission to review a submission from another user', function (): void {
    $user = createUser();
    $reviewer = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsReview);
    $announcement = inReviewPolicyAnnouncement($user, $user);

    expect(Gate::forUser($reviewer)->allows('review', $announcement))->toBeTrue();
});

it('denies review without the review permission', function (): void {
    $user = createUser();
    $reviewer = createUser();
    $announcement = inReviewPolicyAnnouncement($user, $user);

    expect(Gate::forUser($reviewer)->allows('review', $announcement))->toBeFalse();
});

it('denies the announcement author from reviewing even when another user submitted it', function (): void {
    $user = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsReview);
    $submitter = createUser();
    $announcement = inReviewPolicyAnnouncement($user, $submitter);

    expect(Gate::forUser($user)->allows('review', $announcement))->toBeFalse();
});

it('denies the latest submitter but allows an earlier submitter to review', function (): void {
    $user = createUser();
    $earlierSubmitter = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsReview);
    $latestSubmitter = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsReview);
    $announcement = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::InReview)
        ->createdBy($user)
        ->createOne();

    $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Submitted,
        'reviewed_by' => $earlierSubmitter->id,
    ]);
    $announcement->reviews()->create([
        'action' => AnnouncementReviewAction::Resubmitted,
        'reviewed_by' => $latestSubmitter->id,
    ]);

    expect(Gate::forUser($latestSubmitter)->allows('review', $announcement))->toBeFalse()
        ->and(Gate::forUser($earlierSubmitter)->allows('review', $announcement))->toBeTrue();
});

it('allows the dedicated reviewer role to view announcements without write permissions', function (): void {
    $user = createUser();
    $user->assignRole(AdminRole::AdminAnnouncementsReviewer->value);

    $author = createUser();
    $announcement = inReviewPolicyAnnouncement($author, $author);

    expect(Gate::forUser($user)->allows('viewAny', Announcement::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $announcement))->toBeTrue()
        ->and(Gate::forUser($user)->allows('review', $announcement))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Announcement::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $announcement))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $announcement))->toBeFalse();
});

it('denies review outside in-review status and when there is no submission history', function (): void {
    $user = createUser();
    $reviewer = createUserWithAnnouncementPermission(AdminPermission::AdminAnnouncementsReview);
    $announcement = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::Draft)
        ->createdBy($user)
        ->createOne();
    $announcementWithoutSubmission = policyAnnouncementFactory()
        ->withStatus(AnnouncementStatus::InReview)
        ->createdBy($user)
        ->createOne();

    expect(Gate::forUser($reviewer)->allows('review', $announcement))->toBeFalse()
        ->and(Gate::forUser($reviewer)->allows('review', $announcementWithoutSubmission))->toBeFalse();
});

it('denies bulk deletion and restoring even to the announcements manager', function (string $ability): void {
    $user = createUser();
    $user->assignRole(AdminRole::AdminAnnouncementsManager->value);

    $announcement = policyAnnouncementFactory()->createOne();

    $arguments = str_ends_with($ability, 'Any') ? Announcement::class : $announcement;

    expect(Gate::forUser($user)->allows($ability, $arguments))->toBeFalse();
})->with(['deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny']);

it('lets a user dismiss a dismissible announcement shown to them, even twice', function (): void {
    $user = createUser();
    $announcement = policyAnnouncementFactory()->createOne();

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeTrue();

    $announcement->dismissedBy()->attach($user, ['created_at' => now()]);

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeTrue();
});

it('denies dismissing an announcement that is not dismissible or not shown to the user', function (Closure $makeAnnouncement): void {
    $user = createUserWithTwoFactor();
    /** @var Announcement $announcement */
    $announcement = $makeAnnouncement();

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeFalse();
})->with([
    'not dismissible' => [fn (): Announcement => policyAnnouncementFactory()->notDismissible()->createOne()],
    'expired' => [fn (): Announcement => policyAnnouncementFactory()->expired()->createOne()],
    'scheduled' => [fn (): Announcement => policyAnnouncementFactory()->scheduled()->createOne()],
    'for another audience' => [fn (): Announcement => policyAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne()],
]);
