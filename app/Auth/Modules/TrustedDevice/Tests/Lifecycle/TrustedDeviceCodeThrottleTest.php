<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

const CODE_THROTTLE_ATTEMPTS = 5;

/**
 * Send the same wrong code to a route that checks a two-factor code until its budget is spent.
 *
 * @param  array<string, string>  $data
 */
function exhaustCodeAttempts(TestCase $testCase, User $user, string $method, string $uri, array $data): void
{
    foreach (range(1, CODE_THROTTLE_ATTEMPTS) as $attempt) {
        $testCase->actingAs($user)->call($method, $uri, $data)
            ->assertFound();
    }
}

/**
 * @param  array<string, string>  $data
 * @return TestResponse<Response>
 */
function sendCodeAttempt(TestCase $testCase, User $user, string $method, string $uri, array $data): TestResponse
{
    return $testCase->actingAs($user)->call($method, $uri, $data);
}

/**
 * The method, URL and body of a request that checks a two-factor code, for the given route.
 *
 * @return array{0: string, 1: string, 2: array<string, string>}
 */
function codeCheckingRequest(string $routeLabel, User $user): array
{
    return match ($routeLabel) {
        'store' => ['POST', route('user.trusted-devices.store'), ['otp_code' => '000000']],
        'renew' => ['POST', route('user.trusted-devices.renew', createTrustedDevice($user)), ['otp_code' => '000000']],
        'reactivate' => ['POST', route('user.trusted-devices.reactivate', createTrustedDevice($user, ['deleted_at' => now()])), ['otp_code' => '000000']],
        default => ['DELETE', route('setting.security.two-factor-authentication.destroy'), ['password' => 'password', 'code' => '000000']],
    };
}

it('answers 429 after five attempts on a route that checks a two-factor code', function (string $routeLabel): void {
    $user = createUserWithTwoFactor();
    [$method, $url, $data] = codeCheckingRequest($routeLabel, $user);

    exhaustCodeAttempts($this, $user, $method, $url, $data);

    sendCodeAttempt($this, $user, $method, $url, $data)->assertTooManyRequests();
})->with(['store', 'renew', 'reactivate', 'disable two-factor']);

it('shares one attempt budget across the routes that check a two-factor code', function (): void {
    $user = createUserWithTwoFactor();

    exhaustCodeAttempts($this, $user, 'POST', route('user.trusted-devices.store'), ['otp_code' => '000000']);

    sendCodeAttempt($this, $user, 'DELETE', route('setting.security.two-factor-authentication.destroy'), ['password' => 'password', 'code' => '000000'])
        ->assertTooManyRequests();
});

it('does not limit another user because of the attempts of one user', function (): void {
    $user = createUserWithTwoFactor();
    $otherUser = createUserWithTwoFactor();

    exhaustCodeAttempts($this, $user, 'POST', route('user.trusted-devices.store'), ['otp_code' => '000000']);

    sendCodeAttempt($this, $otherUser, 'POST', route('user.trusted-devices.store'), ['otp_code' => '000000'])
        ->assertFound();
});
