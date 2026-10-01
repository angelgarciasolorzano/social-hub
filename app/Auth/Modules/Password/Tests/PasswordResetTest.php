<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('renders the password reset link screen', function (): void {
    $testResponse = $this->get(route('password.request'));

    $testResponse->assertOk();
});

it('sends a reset notification when the email belongs to a user', function (): void {
    Notification::fake();

    $user = createUser();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the password reset screen from the sent notification token', function (): void {
    Notification::fake();

    $user = createUser();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $resetPassword): true {
        $testResponse = $this->get(route('password.reset', $resetPassword->token));

        $testResponse->assertOk();

        return true;
    });
});

it('resets the password with a valid token', function (): void {
    Notification::fake();

    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user, ['name' => 'Personal phone']);
    $secondTrustedDevice = createTrustedDevice($user, ['name' => 'Work laptop']);
    $originalRememberToken = $user->remember_token;

    $otherUser = createUser();
    $otherUsersTrustedDevice = createTrustedDevice($otherUser);
    createTrustedDeviceEvent($otherUser, ['action' => TrustedDeviceAction::Created]);

    $expectedIp = '203.0.113.30';
    $expectedUserAgent = 'Password reset regression test';

    Event::fake([PasswordReset::class]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $resetPassword) use ($expectedIp, $expectedUserAgent, $user): true {
        $testResponse = $this->withServerVariables(['REMOTE_ADDR' => $expectedIp])
            ->withHeaders(['User-Agent' => $expectedUserAgent])
            ->post(route('password.store'), [
                'token' => $resetPassword->token,
                'email' => $user->email,
                'password' => 'new-reset-password',
                'password_confirmation' => 'new-reset-password',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });

    $this->assertSoftDeleted($firstTrustedDevice);
    $this->assertSoftDeleted($secondTrustedDevice);
    $this->assertNotSoftDeleted($otherUsersTrustedDevice);

    $revocationEvents = TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', TrustedDeviceAction::RevokedAll)
        ->get();

    $user->refresh();

    expect(Hash::check('new-reset-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($originalRememberToken)
        ->and($user->trustedDevices()->exists())->toBeFalse()
        ->and($otherUser->trustedDevices()->whereKey($otherUsersTrustedDevice->getKey())->exists())
        ->toBeTrue()
        ->and($revocationEvents)->toHaveCount(2)
        ->and($revocationEvents->pluck('device_label')->all())->toContain('Personal phone', 'Work laptop')
        ->and($revocationEvents->pluck('ip')->unique()->values()->all())->toBe([$expectedIp])
        ->and($revocationEvents->pluck('user_agent')->unique()->values()->all())->toBe([$expectedUserAgent])
        ->and(TrustedDeviceEvent::query()->where('user_id', $otherUser->id)->count())->toBe(1);

    Event::assertDispatched(PasswordReset::class);
});

it('rejects an invalid password reset token', function (): void {
    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user);
    $secondTrustedDevice = createTrustedDevice($user);
    $originalRememberToken = $user->remember_token;

    Event::fake([PasswordReset::class]);

    $testResponse = $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $testResponse->assertSessionHasErrors('email');

    $this->assertNotSoftDeleted($firstTrustedDevice);
    $this->assertNotSoftDeleted($secondTrustedDevice);

    expect(TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', TrustedDeviceAction::RevokedAll)
        ->count())->toBe(0);

    $user->refresh();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->remember_token)->toBe($originalRememberToken)
        ->and($user->trustedDevices()->count())->toBe(2);

    Event::assertNotDispatched(PasswordReset::class);
});
