<?php

declare(strict_types=1);

use App\User\Enums\AccountStatus;
use App\User\Models\User;

const SUSPENDED_CHALLENGE_RECOVERY_CODE = 'suspended-recovery-code-0001';

/**
 * Suspend an account that already passed the password step of the login.
 */
function suspendDuringChallenge(User $user): User
{
    $user->forceFill(['status' => AccountStatus::Suspended])->save();

    return $user;
}

it('sends a suspended account back to the login when it opens the challenge', function (): void {
    $user = suspendDuringChallenge(createUserWithTwoFactor());

    $this->withSession(['login.id' => $user->id, 'login.remember' => true])
        ->get(route('two-factor.login'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE])
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember');

    $this->assertGuest();
});

it('rejects a valid authenticator code from a suspended account', function (): void {
    $user = createUserWithTwoFactor();
    $otp = validOtpFor($user);
    suspendDuringChallenge($user);

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['code' => $otp])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest();
});

it('does not consume a recovery code sent by a suspended account', function (): void {
    $user = suspendDuringChallenge(createUserWithRecoveryCodes(SUSPENDED_CHALLENGE_RECOVERY_CODE));

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['recovery_code' => SUSPENDED_CHALLENGE_RECOVERY_CODE])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->refresh()->recoveryCodes())->toContain(SUSPENDED_CHALLENGE_RECOVERY_CODE);
});

it('still lets an active account complete the challenge', function (): void {
    $user = createUserWithTwoFactor();

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['code' => validOtpFor($user)])
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});
