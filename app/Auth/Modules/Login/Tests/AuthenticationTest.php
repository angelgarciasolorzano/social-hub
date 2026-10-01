<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

it('renders the login page with reset-password availability and status', function (): void {
    $statusMessage = 'Check your inbox.';

    $testResponse = $this->withSession(['status' => $statusMessage])
        ->get(route('login'));

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('auth/login/Login')
        ->where('canResetPassword', true)
        ->where('status', $statusMessage));
});

it('authenticates a user with valid credentials and redirects home', function (): void {
    $user = createUser();

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $testResponse->assertRedirect(route('home', absolute: false));
});

it('keeps guests unauthenticated when login credentials are invalid', function (bool $existingAccount): void {
    $email = 'missing-login@example.test';
    $password = 'password';

    if ($existingAccount) {
        $user = createUser();
        $email = $user->email;
        $password = 'wrong-password';
    }

    $testResponse = $this->post(route('login.store'), [
        'email' => $email,
        'password' => $password,
    ]);

    $testResponse->assertSessionHasErrors('email');
    $this->assertGuest();
})->with([
    'wrong password for an existing account' => [true],
    'email without an account' => [false],
]);

it('logs out the authenticated user and redirects to the root path', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $testResponse->assertRedirect('/');
});

it('redirects users with two-factor authentication enabled to the challenge', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUser();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $testResponse->assertRedirect(route('two-factor.login'));
    $testResponse->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

it('blocks the sixth login attempt after five invalid credentials', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'login-throttle@example.test'])->save();
    $throttleKey = 'login-throttle@example.test|127.0.0.1';

    RateLimiter::clear($throttleKey);
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $attemptNumber) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    expect(RateLimiter::attempts($throttleKey))->toBe(5);

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $testResponse->assertSessionHasErrors('email');

    Event::assertDispatched(Lockout::class);
    $this->assertGuest();
});

it('clears the failed-attempt counter after a successful login', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'login-counter@example.test'])->save();
    $throttleKey = 'login-counter@example.test|127.0.0.1';

    RateLimiter::clear($throttleKey);
    RateLimiter::increment($throttleKey, amount: 4);

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $testResponse->assertRedirect(route('home', absolute: false));

    expect(RateLimiter::attempts($throttleKey))->toBe(0);
});
