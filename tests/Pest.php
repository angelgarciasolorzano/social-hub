<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(
        'Feature',
        __DIR__.'/../app/Modules/Comment/Tests',
        __DIR__.'/../app/Modules/Like/Tests',
        __DIR__.'/../app/Modules/Post/Tests',
        __DIR__.'/../app/Modules/Admin/Tests',
        __DIR__.'/../app/Modules/ActivityLog/Tests',
        __DIR__.'/../app/Auth/Modules/TrustedDevice/Tests',
        __DIR__.'/../app/Auth/Modules/Password/Tests',
        __DIR__.'/../app/Auth/Modules/Login/Tests',
        __DIR__.'/../app/Auth/Modules/Register/Tests',
        __DIR__.'/../app/Auth/Modules/Email/Tests',
        __DIR__.'/../app/User/Modules/TwoFactor/Tests',
        __DIR__.'/../app/User/Modules/AccountSettings/Tests',
        __DIR__.'/../app/User/Tests',
    );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A realistic desktop Chrome/Windows User-Agent, for tests that need a
 * deterministic device fingerprint (e.g. TrustedDevice matching).
 */
function chromeWindowsUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}

/**
 * Create a persisted User. `User` resolves its factory via the #[UseFactory]
 * attribute, which Larastan doesn't infer on its own, so `User::factory()`
 * alone type-checks as mixed (see App\User\Database\Seeders\UserSeeder for the same
 * `Factory<User>` workaround in application code).
 */
function createUser(): User
{
    /** @var Factory<User> $factory */
    $factory = User::factory();

    return $factory->createOne();
}

/**
 * Create a persisted Post, same #[UseFactory] workaround as createUser().
 * Pass `$user` to attach it to an existing owner, or omit it to let the
 * factory create its own.
 *
 * @param  array<string, mixed>  $attributes
 */
function createPost(?User $user = null, array $attributes = []): Post
{
    /** @var Factory<Post> $factory */
    $factory = Post::factory();

    if ($user instanceof User) {
        $factory = $factory->for($user);
    }

    return $factory->createOne($attributes);
}

/**
 * Create a persisted Comment, same #[UseFactory] workaround as createUser().
 * Pass `$user` to attach it to an existing author, or omit it to let the
 * factory create its own. By default it comments on a new Post.
 *
 * @param  array<string, mixed>  $attributes
 */
function createComment(?User $user = null, array $attributes = []): Comment
{
    /** @var Factory<Comment> $factory */
    $factory = Comment::factory();

    if ($user instanceof User) {
        $factory = $factory->for($user);
    }

    return $factory->createOne($attributes);
}

/**
 * Create a persisted Comment on the given Post, or a reply when given another Comment.
 * Pass `$user` to attach it to an existing author, or omit it to let the factory create its own.
 *
 * @param  array<string, mixed>  $attributes
 */
function createCommentOn(Post|Comment $commentable, ?User $user = null, array $attributes = []): Comment
{
    return createComment($user, [
        ...$attributes,
        'commentable_id' => $commentable->id,
        'commentable_type' => $commentable->getMorphClass(),
    ]);
}

/**
 * Create the given number of comments on a Post, or replies when given another Comment.
 *
 * @return list<Comment>
 */
function createCommentsOn(Post|Comment $commentable, int $total, ?User $user = null): array
{
    return array_map(
        static fn (int $position): Comment => createCommentOn($commentable, $user),
        range(1, $total),
    );
}

/**
 * Create a persisted Like by the given user (or a new one) on the given Post or Comment.
 * Omit `$likeable` to like a new Post.
 */
function createLike(?User $user = null, Post|Comment|null $likeable = null): Like
{
    /** @var Factory<Like> $factory */
    $factory = Like::factory();

    if ($user instanceof User) {
        $factory = $factory->for($user);
    }

    if ($likeable !== null) {
        $factory = $factory->for($likeable, 'likeable');
    }

    return $factory->createOne();
}

/**
 * Headers of an Inertia partial reload that asks a page only for the given prop.
 * The caller must already be acting as a user, since the version is read from the home page.
 *
 * @return array<string, string>
 */
function inertiaPartialHeaders(TestCase $testCase, string $component, string $prop): array
{
    /** @var array{version: string} $page */
    $page = $testCase->get(route('home'))->inertiaPage();

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $page['version'],
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $prop,
    ];
}

/**
 * Create a persisted TrustedDevice, same #[UseFactory] workaround as
 * createUser(). Pass `$user` to attach it to an existing owner, or omit it
 * to let the factory create its own.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTrustedDevice(?User $user = null, array $attributes = []): TrustedDevice
{
    /** @var Factory<TrustedDevice> $factory */
    $factory = TrustedDevice::factory();

    if ($user instanceof User) {
        $factory = $factory->for($user);
    }

    return $factory->createOne($attributes);
}

/**
 * Create a persisted TrustedDeviceEvent, same #[UseFactory] workaround as
 * createUser(). `created_at` is force-filled after creation, since it isn't
 * mass-assignable (see the model's #[Fillable] list), so date-filter tests
 * can backdate an event deterministically.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTrustedDeviceEvent(?User $user = null, array $attributes = []): TrustedDeviceEvent
{
    /** @var Factory<TrustedDeviceEvent> $factory */
    $factory = TrustedDeviceEvent::factory();

    if ($user instanceof User) {
        $factory = $factory->for($user);
    }

    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $trustedDeviceEvent = $factory->createOne($attributes);

    if ($createdAt !== null) {
        $trustedDeviceEvent->forceFill(['created_at' => $createdAt])->save();
    }

    return $trustedDeviceEvent;
}

/**
 * Create a persisted User with confirmed two-factor authentication, using the
 * factory state: a real TOTP secret and Fortify-format recovery codes, so
 * tests can compute a genuinely valid code with validOtpFor().
 */
function createUserWithTwoFactor(): User
{
    /** @var Factory<User> $factory */
    $factory = User::factory();

    return $factory->twoFactorConfirmed()->createOne();
}

/**
 * Create a persisted User with two-factor authentication enabled and exactly
 * the given recovery codes stored.
 */
function createUserWithRecoveryCodes(string ...$recoveryCodes): User
{
    $user = createUserWithTwoFactor();

    $user->forceFill([
        'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
    ])->save();

    return $user;
}

/**
 * Compute the current valid TOTP code for a user created via
 * createUserWithTwoFactor().
 */
function validOtpFor(User $user): string
{
    /** @var string $encryptedSecret */
    $encryptedSecret = $user->two_factor_secret;

    /** @var Google2FA $google2fa */
    $google2fa = resolve(Google2FA::class);

    return $google2fa->getCurrentOtp(decrypt($encryptedSecret));
}

/**
 * Let a TOTP code be accepted again within its window; Fortify otherwise
 * rejects a replayed code.
 */
function allowOtpReuse(string $otp): void
{
    Cache::forget('fortify.2fa_codes.'.md5($otp));
}

/**
 * Cache key under which TrustedDeviceDashboardCache stores a user's stats.
 */
function trustedDeviceStatsCacheKey(User $user): string
{
    return \sprintf('trusted-device:dashboard:%s:stats', $user->id);
}

/**
 * Cache key under which TrustedDeviceDashboardCache stores a user's recent activity.
 */
function trustedDeviceRecentActivityCacheKey(User $user): string
{
    return \sprintf('trusted-device:dashboard:%s:recent-activity', $user->id);
}

/**
 * Drop the dashboard stats and recent activity cache entries of the given users.
 */
function forgetTrustedDeviceDashboardCache(User ...$users): void
{
    foreach ($users as $user) {
        Cache::forget(trustedDeviceStatsCacheKey($user));
        Cache::forget(trustedDeviceRecentActivityCacheKey($user));
    }
}

/**
 * Populate the user's dashboard stats cache and assert it is warm.
 */
function warmTrustedDeviceStatsCache(User $user): void
{
    resolve(TrustedDeviceDashboardCache::class)->stats($user);

    expect(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
}

/**
 * A request carrying the IP and User-Agent that TrustedDeviceEvent::record() snapshots.
 */
function trustedDeviceAuditRequest(): Request
{
    return Request::create('/', 'POST', server: [
        'REMOTE_ADDR' => trustedDeviceFingerprint()['ip'],
        'HTTP_USER_AGENT' => trustedDeviceFingerprint()['user_agent'],
    ]);
}

/**
 * Fingerprint attributes (user agent, OS and IP) that identify the same device.
 *
 * @return array{user_agent: string, os_name: string, ip: string}
 */
function trustedDeviceFingerprint(): array
{
    return [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '203.0.113.10',
    ];
}

/**
 * Count the audit events of one action recorded for the given user.
 */
function countTrustedDeviceEvents(User $user, TrustedDeviceAction $trustedDeviceAction): int
{
    return TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', $trustedDeviceAction)
        ->count();
}
