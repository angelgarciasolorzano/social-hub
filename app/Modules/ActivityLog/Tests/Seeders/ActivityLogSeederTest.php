<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Modules\ActivityLog\Database\Seeders\ActivityLogSeeder;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Database\Factories\AnnouncementReviewFactory;
use App\Modules\Announcement\Enums\AnnouncementReviewAction;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementReview;
use App\Providers\AppServiceProvider;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

const ACTIVITY_LOG_SEEDER_SENTINEL_TEXT = 'Texto centinela del seeder de actividad';

const ACTIVITY_LOG_SEEDER_REVIEW_SENTINEL_COMMENT = 'Comentario privado centinela de revisión';

/**
 * Seed a small, known set of data for the activity seeder to describe: Angel as the
 * bootstrap administrator, one more administrator, posts, comments in every state,
 * likes and trusted device events.
 *
 * @return array{angel: User, administrator: User}
 */
function seedActivityLogSourceData(TestCase $testCase): array
{
    $testCase->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->forceFill(['email' => UserSeeder::TEST_USER_EMAIL])->save();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminActivityReader->value);

    $administrator = createUser();
    $administrator->assignRole(AdminRole::Admin->value, AdminRole::AdminPostsReader->value);

    $author = createUserWithTwoFactor();
    $post = createPost($author, ['content' => ACTIVITY_LOG_SEEDER_SENTINEL_TEXT]);
    createPost($author);

    $rootComment = createCommentOn($post, $author, ['content' => ACTIVITY_LOG_SEEDER_SENTINEL_TEXT]);
    createCommentOn($rootComment, $administrator, ['content' => ACTIVITY_LOG_SEEDER_SENTINEL_TEXT]);
    createCommentOn($post, $administrator, ['edited_at' => now()]);
    $rootComment->delete();

    createLike($administrator, $post);
    createLike($author, $rootComment);

    createTrustedDeviceEvent($author, ['action' => TrustedDeviceAction::Created]);
    createTrustedDeviceEvent($author, ['action' => TrustedDeviceAction::Revoked]);

    return ['angel' => $user, 'administrator' => $administrator];
}

/**
 * Create a seeded-style status change of a new account, made by the given administrator.
 */
function createSeededStatusChange(User $user, bool $isSuspension, string $createdAt): AccountStatusChange
{
    /** @var AccountStatusChangeFactory $factory */
    $factory = AccountStatusChange::factory();
    $factory = $isSuspension ? $factory->suspended() : $factory->reactivated();

    return $factory->changedBy($user)->createOne([
        'reason' => ACTIVITY_LOG_SEEDER_SENTINEL_TEXT,
        'created_at' => $createdAt,
    ]);
}

/**
 * Create a seeded-style announcement by the given administrator with the given dates; every one has the same content.
 */
function createSeededAnnouncement(User $user, string $createdAt, string $updatedAt, ?string $endsAt = null): Announcement
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory->createdBy($user)->createOne([
        'title' => ACTIVITY_LOG_SEEDER_SENTINEL_TEXT,
        'body' => '<p>'.ACTIVITY_LOG_SEEDER_SENTINEL_TEXT.'</p>',
        'starts_at' => $createdAt,
        'ends_at' => $endsAt,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);
}

/**
 * Create an announcement review with the given action and actor.
 */
function createSeededAnnouncementReview(
    Announcement $announcement,
    AnnouncementReviewAction $announcementReviewAction,
    User $user,
    string $createdAt,
    string $comment,
): AnnouncementReview {
    /** @var AnnouncementReviewFactory $factory */
    $factory = AnnouncementReview::factory();

    return $factory->for($announcement)->createOne([
        'action' => $announcementReviewAction,
        'comment' => $comment,
        'reviewed_by' => $user->id,
        'created_at' => $createdAt,
    ]);
}

/**
 * The seeded activity of the given announcement for the given event.
 */
function seededAnnouncementActivity(Announcement $announcement, string $eventName): ?ActivityLog
{
    return ActivityLog::query()
        ->where('event', $eventName)
        ->where('subject_type', Announcement::MORPH_NAME)
        ->where('subject_id', $announcement->id)
        ->first();
}

/**
 * Number of seeded activities stored for the given event.
 */
function countSeededActivities(string $eventName): int
{
    return ActivityLog::query()->where('event', $eventName)->count();
}

it('creates one content activity per post and per comment, in the right variant', function (): void {
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    expect(countSeededActivities('content.post_created'))->toBe(2)
        ->and(countSeededActivities('content.comment_created'))->toBe(2)
        ->and(countSeededActivities('content.comment_replied'))->toBe(1)
        ->and(countSeededActivities('content.comment_updated'))->toBe(1)
        ->and(countSeededActivities('content.comment_deleted'))->toBe(1);
});

it('creates one like activity per like and one mirror per trusted device event', function (): void {
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    expect(countSeededActivities('likes.added'))->toBe(2)
        ->and(countSeededActivities('trusted_devices.created'))->toBe(1)
        ->and(countSeededActivities('trusted_devices.revoked'))->toBe(1);
});

it('points every content, like and device activity to an existing record', function (): void {
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    $activitiesWithoutSubject = ActivityLog::query()
        ->whereIn('log_name', ['content', 'likes', 'trusted_devices'])
        ->with('subject')
        ->get()
        ->filter(fn (ActivityLog $activityLog): bool => $activityLog->subject === null);

    expect($activitiesWithoutSubject)->toBeEmpty();
});

it('records the two-factor confirmation and only allowed properties for authentication', function (): void {
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    $propertyKeys = ActivityLog::query()
        ->where('log_name', 'auth')
        ->get()
        ->flatMap(fn (ActivityLog $activityLog): array => $activityLog->properties?->keys()->all() ?? [])
        ->unique()
        ->values()
        ->all();

    expect(countSeededActivities('auth.two_factor_confirmed'))->toBe(1)
        ->and(array_diff($propertyKeys, ['guard', 'ip', 'user_agent']))->toBeEmpty()
        ->and(ActivityLog::query()->where('event', 'auth.login_failed')->whereNotNull('causer_id')->exists())->toBeFalse();
});

it('records Angel bootstrapping the panel and granting every role of the other administrators', function (): void {
    ['angel' => $angel, 'administrator' => $administrator] = seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    $roleGrants = ActivityLog::query()->where('event', 'admin.role_granted')->get();

    expect(ActivityLog::query()->where('event', 'admin.bootstrapped')->sole())
        ->causer_id->toBe($angel->id)
        ->subject_id->toBe($angel->id)
        ->and($roleGrants->pluck('causer_id')->unique()->all())->toBe([$angel->id])
        ->and($roleGrants->pluck('subject_id')->unique()->all())->toBe([$administrator->id])
        ->and($roleGrants->map(fn (ActivityLog $activityLog): mixed => $activityLog->properties?->get('role'))->sort()->values()->all())
        ->toBe([AdminRole::Admin->value, AdminRole::AdminPostsReader->value]);
});

it('records a few role permissions Angel revoked and later granted back', function (): void {
    ['angel' => $angel] = seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    $revocations = ActivityLog::query()->where('event', 'admin.role_permission_revoked')->get();
    $grants = ActivityLog::query()->where('event', 'admin.role_permission_granted')->get();

    expect($revocations->count())->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(8)
        ->and($grants)->toHaveCount($revocations->count())
        ->and($revocations->merge($grants)->pluck('causer_id')->unique()->all())->toBe([$angel->id])
        ->and($revocations->merge($grants)->pluck('subject_type')->unique()->all())->toBe([AppServiceProvider::ROLE_MORPH_NAME]);

    foreach ($revocations as $revocation) {
        $role = Role::query()->findOrFail($revocation->subject_id);
        $permissionName = $revocation->getProperty('permission');

        $grant = $grants->first(static fn (ActivityLog $activityLog): bool => $activityLog->subject_id === $revocation->subject_id
            && $activityLog->getProperty('permission') === $permissionName);

        expect($grant)->toBeInstanceOf(ActivityLog::class)
            ->and($grant?->created_at?->getTimestamp())->toBeGreaterThanOrEqual($revocation->created_at?->getTimestamp() ?? PHP_INT_MAX)
            ->and($revocation->getProperty('role'))->toBe($role->name)
            ->and(is_string($permissionName) && $role->hasPermissionTo($permissionName))->toBeTrue();
    }
});

it('never stores the content of posts or comments', function (): void {
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    expect(DB::table('activity_log')->get()->toJson())->not->toContain(ACTIVITY_LOG_SEEDER_SENTINEL_TEXT);
});

it('spreads the dates over the last sixty days without reaching the future', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));
    seedActivityLogSourceData($this);

    $this->seed(ActivityLogSeeder::class);

    $builder = ActivityLog::query()->where('log_name', '!=', 'trusted_devices');

    expect((clone $builder)->where('created_at', '>', now())->exists())->toBeFalse()
        ->and((clone $builder)->where('created_at', '<', now()->subDays(60))->exists())->toBeFalse();
});

it('does not add activity when the log already has records', function (): void {
    seedActivityLogSourceData($this);
    $this->seed(ActivityLogSeeder::class);
    $seededCount = ActivityLog::query()->count();

    $this->seed(ActivityLogSeeder::class);

    expect(ActivityLog::query()->count())->toBe($seededCount);
});

it('seeds nothing on an empty database', function (): void {
    $this->seed(ActivityLogSeeder::class);

    expect(ActivityLog::query()->exists())->toBeFalse();
});

it('mirrors every account status change with its event, subject, administrator and date', function (): void {
    $user = createUser();
    $accountStatusChange = createSeededStatusChange($user, isSuspension: true, createdAt: '2026-10-01 10:00:00');
    $reactivation = createSeededStatusChange($user, isSuspension: false, createdAt: '2026-10-05 10:00:00');

    $this->seed(ActivityLogSeeder::class);

    $activityLog = ActivityLog::query()->where('event', 'admin.account_suspended')->sole();
    $reactivationActivity = ActivityLog::query()->where('event', 'admin.account_reactivated')->sole();

    expect($activityLog->subject?->is($accountStatusChange))->toBeTrue()
        ->and($activityLog->causer?->is($user))->toBeTrue()
        ->and($activityLog->properties?->get('target_user_id'))->toBe($accountStatusChange->user_id)
        ->and($activityLog->created_at?->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and($reactivationActivity->subject?->is($reactivation))->toBeTrue();
});

it('never stores the reason of an account status change', function (): void {
    createSeededStatusChange(createUser(), isSuspension: true, createdAt: '2026-10-01 10:00:00');

    $this->seed(ActivityLogSeeder::class);

    expect(ActivityLog::query()->where('event', 'admin.account_suspended')->sole()->properties?->keys()->all())
        ->toEqualCanonicalizing(['target_user_id', 'ip', 'user_agent'])
        ->and(DB::table('activity_log')->get()->toJson())->not->toContain(ACTIVITY_LOG_SEEDER_SENTINEL_TEXT);
});

it('records the creation of every announcement by its author at its creation date', function (): void {
    $user = createUser();
    $announcement = createSeededAnnouncement($user, '2026-10-01 10:00:00', '2026-10-01 10:00:00');

    $this->seed(ActivityLogSeeder::class);

    $activityLog = seededAnnouncementActivity($announcement, 'admin.announcement_created');

    expect($activityLog?->causer?->is($user))->toBeTrue()
        ->and($activityLog?->created_at?->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and(countSeededActivities('admin.announcement_updated'))->toBe(0)
        ->and(countSeededActivities('admin.announcement_unpublished'))->toBe(0);
});

it('records an edit when the announcement was saved after being created', function (): void {
    $announcement = createSeededAnnouncement(createUser(), '2026-10-01 10:00:00', '2026-10-03 09:00:00');

    $this->seed(ActivityLogSeeder::class);

    $activityLog = seededAnnouncementActivity($announcement, 'admin.announcement_updated');

    expect($activityLog?->created_at?->toDateTimeString())->toBe('2026-10-03 09:00:00')
        ->and($activityLog?->properties?->get('changed_attributes'))->toBeArray()->not->toBeEmpty();
});

it('records an unpublish when the end date is the moment of the last save', function (): void {
    $announcement = createSeededAnnouncement(createUser(), '2026-10-01 10:00:00', '2026-10-04 08:00:00', endsAt: '2026-10-04 08:00:00');

    $this->seed(ActivityLogSeeder::class);

    expect(seededAnnouncementActivity($announcement, 'admin.announcement_unpublished')?->created_at?->toDateTimeString())->toBe('2026-10-04 08:00:00')
        ->and(seededAnnouncementActivity($announcement, 'admin.announcement_updated'))->toBeNull();
});

it('records a later announcement with the same content as a duplicate of the first one', function (): void {
    $user = createUser();
    $announcement = createSeededAnnouncement($user, '2026-10-01 10:00:00', '2026-10-01 10:00:00');
    $copy = createSeededAnnouncement($user, '2026-10-05 10:00:00', '2026-10-05 10:00:00');

    $this->seed(ActivityLogSeeder::class);

    expect(seededAnnouncementActivity($copy, 'admin.announcement_duplicated')?->properties?->get('source_announcement_id'))->toBe($announcement->id)
        ->and(seededAnnouncementActivity($copy, 'admin.announcement_created'))->toBeNull()
        ->and(seededAnnouncementActivity($announcement, 'admin.announcement_created'))->not->toBeNull();
});

it('mirrors every announcement review with its actor, announcement and history id', function (AnnouncementReviewAction $announcementReviewAction, string $eventName, bool $isAuthorAction): void {
    $user = createUser();
    $actor = $isAuthorAction ? $user : createUser();
    $announcement = createSeededAnnouncement($user, '2026-10-01 10:00:00', '2026-10-01 10:00:00');
    $reviewCreatedAt = '2026-10-06 12:00:00';
    $announcementReview = createSeededAnnouncementReview(
        $announcement,
        $announcementReviewAction,
        $actor,
        $reviewCreatedAt,
        ACTIVITY_LOG_SEEDER_REVIEW_SENTINEL_COMMENT,
    );

    $this->seed(ActivityLogSeeder::class);

    $activityLog = ActivityLog::query()
        ->where('event', $eventName)
        ->get()
        ->sole(fn (ActivityLog $candidateActivityLog): bool => $candidateActivityLog->properties?->get('announcement_review_id') === $announcementReview->id);

    expect($activityLog->subject?->is($announcement))->toBeTrue()
        ->and($activityLog->causer?->is($actor))->toBeTrue()
        ->and($activityLog->created_at?->toDateTimeString())->toBe($reviewCreatedAt)
        ->and($activityLog->properties?->keys()->all())
        ->toEqualCanonicalizing(['announcement_review_id', 'ip', 'user_agent']);
})->with([
    'submission' => [AnnouncementReviewAction::Submitted, 'admin.announcement_submitted', true],
    'resubmission' => [AnnouncementReviewAction::Resubmitted, 'admin.announcement_submitted', true],
    'approval' => [AnnouncementReviewAction::Approved, 'admin.announcement_approved', false],
    'rejection' => [AnnouncementReviewAction::Rejected, 'admin.announcement_rejected', false],
]);

it('never stores review comments or announcement content in activity properties', function (): void {
    $announcement = createSeededAnnouncement(createUser(), '2026-10-01 10:00:00', '2026-10-02 10:00:00');
    createSeededAnnouncementReview(
        $announcement,
        AnnouncementReviewAction::Approved,
        createUser(),
        '2026-10-02 10:00:00',
        ACTIVITY_LOG_SEEDER_REVIEW_SENTINEL_COMMENT,
    );

    $this->seed(ActivityLogSeeder::class);

    expect(countSeededActivities('admin.announcement_created'))->toBe(1)
        ->and(DB::table('activity_log')->get()->toJson())
        ->not->toContain(ACTIVITY_LOG_SEEDER_SENTINEL_TEXT)
        ->not->toContain(ACTIVITY_LOG_SEEDER_REVIEW_SENTINEL_COMMENT);
});
