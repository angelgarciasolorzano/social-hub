<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
    ->in('Feature', __DIR__.'/../app/Auth/Modules/TrustedDevice/Tests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

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
 * alone type-checks as mixed (see App\User\Seeders\UserSeeder for the same
 * `Factory<User>` workaround in application code).
 */
function createUser(): User
{
    /** @var Factory<User> $factory */
    $factory = User::factory();

    return $factory->createOne();
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
 * Create a persisted User with two-factor authentication enabled, using a
 * real TOTP secret and two 6-digit recovery codes (the format the OTP field
 * itself requires) so tests can compute a genuinely valid code with
 * validOtpFor() or exercise the recovery-code fallback.
 */
function createUserWithTwoFactor(): User
{
    $user = createUser();

    /** @var Google2FA $google2fa */
    $google2fa = resolve(Google2FA::class);

    $user->forceFill([
        'two_factor_secret' => encrypt($google2fa->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['111111', '222222'])),
        'two_factor_confirmed_at' => now(),
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
 * Extract a LengthAwarePaginator-shaped Inertia prop's `data` array with a
 * concrete type, sidestepping TestResponse::inertiaProps()'s untyped mixed.
 *
 * @return array<int, array<string, mixed>>
 */
function paginatedPropData(TestResponse $testResponse, string $prop): array
{
    /** @var array{data: array<int, array<string, mixed>>, total: int} $paginated */
    $paginated = $testResponse->inertiaProps($prop);

    return $paginated['data'];
}

/**
 * Same as paginatedPropData(), but for the paginator's `total` count.
 */
function paginatedPropTotal(TestResponse $testResponse, string $prop): int
{
    /** @var array{data: array<int, array<string, mixed>>, total: int} $paginated */
    $paginated = $testResponse->inertiaProps($prop);

    return $paginated['total'];
}
