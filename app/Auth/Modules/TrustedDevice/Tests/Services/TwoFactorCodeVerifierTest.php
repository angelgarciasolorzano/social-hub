<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Services\TwoFactorCodeVerifier;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery\MockInterface;

const VERIFIER_FIRST_RECOVERY_CODE = 'abcde12345-fghij67890';
const VERIFIER_SECOND_RECOVERY_CODE = 'klmno12345-pqrst67890';

function twoFactorCodeVerifier(): TwoFactorCodeVerifier
{
    return resolve(TwoFactorCodeVerifier::class);
}

/**
 * @return list<string>
 */
function storedRecoveryCodes(User $user): array
{
    /** @var list<string> $recoveryCodes */
    $recoveryCodes = $user->refresh()->recoveryCodes();

    return $recoveryCodes;
}

it('reports confirmed two-factor only when the secret is confirmed', function (): void {
    $user = createUserWithTwoFactor();

    expect(twoFactorCodeVerifier()->hasConfirmedTwoFactor($user))->toBeTrue();
});

it('does not count a pending secret as confirmed two-factor', function (): void {
    $user = createUserWithTwoFactor();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    expect(twoFactorCodeVerifier()->hasConfirmedTwoFactor($user))->toBeFalse();
});

it('does not report confirmed two-factor when it is disabled', function (): void {
    expect(twoFactorCodeVerifier()->hasConfirmedTwoFactor(createUser()))->toBeFalse();
});

it('accepts the current TOTP code', function (): void {
    $user = createUserWithTwoFactor();

    expect(twoFactorCodeVerifier()->verifyTotp($user, validOtpFor($user)))->toBeTrue();
});

it('rejects an invalid TOTP code', function (): void {
    $user = createUserWithTwoFactor();

    expect(twoFactorCodeVerifier()->verifyTotp($user, '000000'))->toBeFalse();
});

it('rejects a TOTP code for a user without a secret', function (): void {
    expect(twoFactorCodeVerifier()->verifyTotp(createUser(), '123456'))->toBeFalse();
});

it('returns false and logs without the code when the secret cannot be decrypted', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '123456')
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'corrupted-secret'));

    $user = createUserWithTwoFactor();
    $user->forceFill(['two_factor_secret' => 'corrupted-secret'])->save();

    expect(twoFactorCodeVerifier()->verifyTotp($user, '123456'))->toBeFalse();
});

it('consumes a valid recovery code and removes it from the stored codes', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);

    $consumed = DB::transaction(
        fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, VERIFIER_FIRST_RECOVERY_CODE)
    );

    expect($consumed)->toBeTrue()
        ->and(storedRecoveryCodes($user))->toBe([VERIFIER_SECOND_RECOVERY_CODE]);
});

it('does not accept the same recovery code twice', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);

    DB::transaction(fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, VERIFIER_FIRST_RECOVERY_CODE));

    $consumedAgain = DB::transaction(
        fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, VERIFIER_FIRST_RECOVERY_CODE)
    );

    expect($consumedAgain)->toBeFalse();
});

it('does not accept the same recovery code from a stale user model', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);
    $staleUser = User::query()->whereKey($user->getKey())->firstOrFail();

    DB::transaction(fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, VERIFIER_FIRST_RECOVERY_CODE));

    $consumedWithStaleModel = DB::transaction(
        fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($staleUser, VERIFIER_FIRST_RECOVERY_CODE)
    );

    expect($consumedWithStaleModel)->toBeFalse();
});

it('rejects a recovery code that belongs to another user', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);
    $otherUser = createUserWithTwoFactor();

    $consumed = DB::transaction(
        fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($otherUser, VERIFIER_FIRST_RECOVERY_CODE)
    );

    expect($consumed)->toBeFalse()
        ->and(storedRecoveryCodes($user))->toContain(VERIFIER_FIRST_RECOVERY_CODE);
});

it('rejects an empty or unknown recovery code without changing the stored codes', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);

    $consumedEmpty = DB::transaction(fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, ''));
    $consumedUnknown = DB::transaction(fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, 'unknown-code'));

    expect($consumedEmpty)->toBeFalse()
        ->and($consumedUnknown)->toBeFalse()
        ->and(storedRecoveryCodes($user))->toBe([VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE]);
});

it('returns false and logs without secrets when the recovery codes are unreadable', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), VERIFIER_FIRST_RECOVERY_CODE)
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'corrupted-codes'));

    $user = createUserWithTwoFactor();
    $user->forceFill(['two_factor_recovery_codes' => 'corrupted-codes'])->save();

    $consumed = DB::transaction(fn (): bool => twoFactorCodeVerifier()->consumeRecoveryCode($user, VERIFIER_FIRST_RECOVERY_CODE));

    expect($consumed)->toBeFalse();
});

it('accepts the current TOTP code as a verification code', function (): void {
    $user = createUserWithTwoFactor();

    expect(twoFactorCodeVerifier()->verifyCode($user, validOtpFor($user)))->toBeTrue();
});

it('accepts a valid recovery code as a verification code and consumes it', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE, VERIFIER_SECOND_RECOVERY_CODE);

    $isValid = DB::transaction(fn (): bool => twoFactorCodeVerifier()->verifyCode($user, VERIFIER_FIRST_RECOVERY_CODE));

    expect($isValid)->toBeTrue()
        ->and(storedRecoveryCodes($user))->toBe([VERIFIER_SECOND_RECOVERY_CODE]);
});

it('does not look for a six-digit code among the recovery codes', function (): void {
    $user = createUserWithRecoveryCodes('123456');

    $isValid = DB::transaction(fn (): bool => twoFactorCodeVerifier()->verifyCode($user, '123456'));

    expect($isValid)->toBeFalse()
        ->and(storedRecoveryCodes($user))->toBe(['123456']);
});

it('does not check a recovery-format code as a TOTP code', function (): void {
    $user = createUserWithRecoveryCodes(VERIFIER_FIRST_RECOVERY_CODE);

    $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('verify');
    });

    $isValid = DB::transaction(fn (): bool => twoFactorCodeVerifier()->verifyCode($user, VERIFIER_FIRST_RECOVERY_CODE));

    expect($isValid)->toBeTrue();
});
