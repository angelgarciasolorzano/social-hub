<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Modules\ActivityLog\Database\Seeders\ActivityLogSeeder;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

const ACTIVITY_LOG_SEEDER_SENTINEL_TEXT = 'Texto centinela del seeder de actividad';

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
