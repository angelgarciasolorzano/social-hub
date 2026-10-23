<?php

declare(strict_types=1);

use App\User\Database\Factories\UserFactory;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

const SUSPENDED_LOGIN_TRUSTED_DEVICE_TOKEN = 'suspended-account-trusted-device-token';

/**
 * Create a suspended account with confirmed two-factor authentication.
 */
function createSuspendedUserWithTwoFactor(): User
{
    /** @var UserFactory $factory */
    $factory = User::factory();

    return $factory->twoFactorConfirmed()->suspended()->createOne();
}

/**
 * @return array{email: string, password: string}
 */
function suspendedLoginCredentials(User $user, string $password = 'password'): array
{
    return ['email' => $user->email, 'password' => $password];
}

it('rejects a suspended account with the right password and shows the suspension message', function (): void {
    $user = createSuspendedUser();

    $this->post(route('login.store'), suspendedLoginCredentials($user))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest();
});

it('rejects a suspended account even with a valid trusted device cookie', function (): void {
    $user = createSuspendedUserWithTwoFactor();
    createTrustedDevice($user, ['token_hash' => hash('sha256', SUSPENDED_LOGIN_TRUSTED_DEVICE_TOKEN)]);

    $this->withCookie('trusted_device', SUSPENDED_LOGIN_TRUSTED_DEVICE_TOKEN)
        ->post(route('login.store'), suspendedLoginCredentials($user))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest();
});

it('does not start the two-factor challenge for a suspended account', function (): void {
    $user = createSuspendedUserWithTwoFactor();

    $this->post(route('login.store'), suspendedLoginCredentials($user))
        ->assertSessionHasErrors('email');

    expect(session()->has('login.id'))->toBeFalse();
});

it('shows the generic failure, not the suspension, when the password is wrong', function (): void {
    $user = createSuspendedUser();

    $this->post(route('login.store'), suspendedLoginCredentials($user, 'wrong-password'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('does not count a suspended login as a failed attempt', function (): void {
    Event::fake([Failed::class]);
    $user = createSuspendedUser();

    $this->post(route('login.store'), suspendedLoginCredentials($user));

    Event::assertNotDispatched(Failed::class);
    expect(RateLimiter::attempts(mb_strtolower($user->email).'|127.0.0.1'))->toBe(0);
});
