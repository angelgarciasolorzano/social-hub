<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Register the current device as the given user, sending a deterministic Chrome/Windows User-Agent.
 *
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function storeTrustedDeviceAs(TestCase $testCase, User $user, array $data = []): TestResponse
{
    return $testCase->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'), $data);
}

it('redirects guests to the login page', function (): void {
    $this->post(route('user.trusted-devices.store'))
        ->assertRedirect(route('login'));
});

it('rejects the registration without creating anything when two-factor is disabled or pending', function (bool $hasPendingSecret): void {
    $user = $hasPendingSecret ? createUserWithTwoFactor() : createUser();

    if ($hasPendingSecret) {
        $user->forceFill(['two_factor_confirmed_at' => null])->save();
    }

    $otp = $hasPendingSecret ? validOtpFor($user) : '123456';

    $testResponse = storeTrustedDeviceAs($this, $user, ['otp_code' => $otp]);

    $testResponse->assertSessionHasErrors('two_factor')
        ->assertCookieMissing('trusted_device');

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'two-factor disabled' => [false],
    'pending secret' => [true],
]);

it('does not ask for the OTP code when two-factor is not confirmed', function (): void {
    $testResponse = storeTrustedDeviceAs($this, createUser());

    $testResponse->assertSessionHasErrors('two_factor')
        ->assertSessionDoesntHaveErrors('otp_code');
});

it('rejects a missing, wrong or recovery-format OTP code without creating anything', function (?string $otp): void {
    $user = createUserWithTwoFactor();

    $testResponse = storeTrustedDeviceAs($this, $user, $otp === null ? [] : ['otp_code' => $otp]);

    $testResponse->assertSessionHasErrors('otp_code')
        ->assertCookieMissing('trusted_device');

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'missing code' => [null],
    'wrong code' => ['000000'],
    'recovery-format code' => ['abcde12345-fghij67890'],
]);

it('answers a wrong OTP code with a fixed message that does not echo the submitted code', function (): void {
    $user = createUserWithTwoFactor();

    $testResponse = storeTrustedDeviceAs($this, $user, ['otp_code' => '000000']);

    $testResponse->assertSessionHasErrors(['otp_code' => 'El código de verificación es incorrecto.']);
});

it('creates a trusted device with an inferred name and issues the cookie when the OTP is valid', function (): void {
    $user = createUserWithTwoFactor();

    $testResponse = storeTrustedDeviceAs($this, $user, ['otp_code' => validOtpFor($user)]);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');
    $testResponse->assertCookie('trusted_device');

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->name)->not->toBeEmpty()
        ->and($trustedDevice->user_agent)->toBe(chromeWindowsUserAgent())
        ->and($trustedDevice->os_name)->toBe('Windows')
        ->and($trustedDevice->is_mobile)->toBeFalse()
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->exists())
        ->toBeTrue();
});

it('accepts a custom device name', function (): void {
    $user = createUserWithTwoFactor();

    storeTrustedDeviceAs($this, $user, ['name' => 'Mi laptop personal', 'otp_code' => validOtpFor($user)]);

    expect(TrustedDevice::query()->where('user_id', $user->id)->sole()->name)
        ->toBe('Mi laptop personal');
});

it('rejects a name longer than 50 characters', function (): void {
    $user = createUserWithTwoFactor();

    $testResponse = storeTrustedDeviceAs($this, $user, ['name' => str_repeat('a', 51), 'otp_code' => validOtpFor($user)]);

    $testResponse->assertSessionHasErrors('name');

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('does not create a duplicate when an active device with the same fingerprint already exists', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '127.0.0.1',
    ]);

    $testResponse = storeTrustedDeviceAs($this, $user, ['otp_code' => validOtpFor($user)]);

    $testResponse->assertInertiaFlash('type', 'error');
    $testResponse->assertCookieMissing('trusted_device');

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->exists())->toBeFalse();
});

it('reports a revoked duplicate as needing reactivation instead of creating a new device', function (): void {
    $user = createUserWithTwoFactor();
    $otp = validOtpFor($user);

    storeTrustedDeviceAs($this, $user, ['otp_code' => $otp]);
    allowOtpReuse($otp);

    TrustedDevice::query()->where('user_id', $user->id)->sole()->delete();

    $testResponse = storeTrustedDeviceAs($this, $user, ['otp_code' => $otp]);

    $testResponse->assertInertiaFlash('type', 'error');

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});
