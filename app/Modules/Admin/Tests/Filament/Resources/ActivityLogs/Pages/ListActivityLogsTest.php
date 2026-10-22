<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The keys of the activities shown on the current page of the list.
 *
 * @param  Testable<ListActivityLogs>  $testable
 * @return list<string>
 */
function visibleActivityLogKeys(Testable $testable): array
{
    return FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), ActivityLog::class);
}

/**
 * Sign in as an administrator who can read the activity log.
 */
function actingAsActivityLogReader(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminActivityReader);
}

/**
 * Count the queries that rendering the first page of the list runs, leaving out the
 * role and permission lookups, which depend on Spatie's cache and not on the rows.
 */
function countActivityLogListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListActivityLogs::class)->assertOk();

    $listQueries = array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $query): bool => preg_match('/\b(permissions|roles)\b/', $query) !== 1,
    );
    DB::disableQueryLog();

    return count($listQueries);
}

/**
 * Create one activity on each kind of subject, performed by a new user.
 */
function createActivityLogsOnEverySubjectType(): void
{
    $user = createUser();
    $post = createPost($user);
    $comment = createCommentOn($post, $user);

    ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::PostCreated)->causedBy($user)->performedOn($post)->createOne();
    ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::CommentCreated)->causedBy($user)->performedOn($comment)->createOne();
    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->causedBy($user)->performedOn(createUser())->createOne();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('denies the list to an administrator without admin.activity.viewAny', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminPostsReader);

    Livewire::test(ListActivityLogs::class)->assertForbidden();

    expect(ActivityLogResource::canAccess())->toBeFalse();
});

it('shows the list and its navigation item to an activity reader', function (): void {
    actingAsActivityLogReader($this);

    Livewire::test(ListActivityLogs::class)->assertOk();

    expect(ActivityLogResource::canAccess())->toBeTrue();
});

it('lists each activity with readable log, event, performer and subject', function (): void {
    $user = actingAsActivityLogReader($this);
    $target = createUser();

    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->causedBy($user)->performedOn($target)->createOne();

    Livewire::test(ListActivityLogs::class)
        ->assertSeeText('Admin')
        ->assertSeeText('Role Granted')
        ->assertSeeText($user->name)
        ->assertSeeText('User #'.$target->id);
});

it('lists anonymous activities and deleted performers or subjects without failing', function (): void {
    actingAsActivityLogReader($this);
    $deletedUser = createUser();
    $deletedComment = createComment();

    ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::LoginFailed)->createOne();
    ActivityLogFactory::new()->forEvent(ActivityLogContentEvent::CommentDeleted)->causedBy($deletedUser)->performedOn($deletedComment)->createOne();

    $deletedUserId = $deletedUser->id;
    $deletedCommentId = $deletedComment->id;
    $deletedComment->forceDelete();
    $deletedUser->delete();

    Livewire::test(ListActivityLogs::class)
        ->assertOk()
        ->assertSeeText('Anonymous')
        ->assertSeeText('Deleted user #'.$deletedUserId)
        ->assertSeeText('Comment #'.$deletedCommentId.' (deleted)');
});

it('orders the newest activity first', function (): void {
    actingAsActivityLogReader($this);

    $olderActivityLog = ActivityLogFactory::new()->createOne();
    $newerActivityLog = ActivityLogFactory::new()->createOne();

    expect(visibleActivityLogKeys(Livewire::test(ListActivityLogs::class)))
        ->toBe([FilamentTableRecords::recordKey($newerActivityLog), FilamentTableRecords::recordKey($olderActivityLog)]);
});

it('filters by log and by event', function (): void {
    actingAsActivityLogReader($this);

    $likeActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogLikeEvent::Added)->createOne();
    $loginActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::UserLoggedIn)->createOne();
    $logoutActivityLog = ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::UserLoggedOut)->createOne();

    $testable = Livewire::test(ListActivityLogs::class)->set('tableFilters.log_name', ['value' => 'auth']);
    $byEvent = Livewire::test(ListActivityLogs::class)->set('tableFilters.event', ['value' => 'auth.logout']);

    expect(visibleActivityLogKeys($testable))
        ->toEqualCanonicalizing([FilamentTableRecords::recordKey($loginActivityLog), FilamentTableRecords::recordKey($logoutActivityLog)])
        ->and(visibleActivityLogKeys($byEvent))->toBe([FilamentTableRecords::recordKey($logoutActivityLog)])
        ->and(visibleActivityLogKeys($testable))->not->toContain(FilamentTableRecords::recordKey($likeActivityLog));
});

it('filters by the user who performed the activity', function (): void {
    actingAsActivityLogReader($this);
    $user = createUser();
    $otherUser = createUser();

    $userActivityLog = ActivityLogFactory::new()->causedBy($user)->createOne();
    ActivityLogFactory::new()->causedBy($otherUser)->createOne();
    ActivityLogFactory::new()->performedOn($user)->createOne();

    $testable = Livewire::test(ListActivityLogs::class)->set('tableFilters.causer.causer_id', $user->id);

    expect(visibleActivityLogKeys($testable))->toBe([FilamentTableRecords::recordKey($userActivityLog)]);
});

it('filters by date range', function (): void {
    actingAsActivityLogReader($this);

    $oldActivityLog = ActivityLogFactory::new()->createOne(['created_at' => '2026-01-10 10:00:00']);
    $recentActivityLog = ActivityLogFactory::new()->createOne(['created_at' => '2026-10-10 10:00:00']);

    $testable = Livewire::test(ListActivityLogs::class)
        ->set('tableFilters.created_at.created_from', '2026-10-01')
        ->set('tableFilters.created_at.created_until', '2026-10-31');

    expect(visibleActivityLogKeys($testable))
        ->toBe([FilamentTableRecords::recordKey($recentActivityLog)])
        ->not->toContain(FilamentTableRecords::recordKey($oldActivityLog));
});

it('runs the same number of queries for a few or many activities, links included', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles(
        $this,
        AdminRole::Admin,
        AdminRole::AdminActivityReader,
        AdminRole::AdminUsersReader,
        AdminRole::AdminPostsReader,
    );

    createActivityLogsOnEverySubjectType();
    $queriesForFewActivities = countActivityLogListQueries();

    foreach (range(1, 3) as $iteration) {
        createActivityLogsOnEverySubjectType();
    }

    expect(countActivityLogListQueries())->toBe($queriesForFewActivities);
});
