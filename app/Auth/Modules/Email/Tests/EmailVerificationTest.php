<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function verificationUrlFor(User $user, string $tampering = ''): string
{
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        $tampering === 'expiry' ? now()->subMinute() : now()->addMinutes(60),
        [
            'id' => $tampering === 'id' ? $user->id + 1 : $user->id,
            'hash' => sha1($tampering === 'hash' ? 'different-email@example.test' : $user->email),
        ],
    );

    return $tampering === 'signature'
        ? str_replace('signature=', 'signature=altered-', $verificationUrl)
        : $verificationUrl;
}

it('redirects guests to login from the verification notice', function (): void {
    $this->get(route('verification.notice'))
        ->assertRedirect(route('login'));
});

it('redirects guests to login from a signed verification link', function (): void {
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 1, 'hash' => sha1('guest@example.test')],
    );

    $this->get($verificationUrl)
        ->assertRedirect(route('login'));
});

it('renders the verification prompt for an unverified user', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();
    $status = 'verification-link-sent';

    $testResponse = $this->withSession(['status' => $status])
        ->actingAs($user)
        ->get(route('verification.notice'));

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('auth/VerifyEmail')
        ->where('status', $status));
});

it('redirects an already verified user from the verification prompt to home', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)->get(route('verification.notice'));

    $testResponse->assertRedirect(route('home', absolute: false));
});

it('verifies an email from a valid signed URL and dispatches Verified once', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $verificationUrl = verificationUrlFor($user);

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertRedirect(route('home', absolute: false).'?verified=1');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatchedOnce(Verified::class);
});

it('rejects a verification URL with :dataset', function (string $tampering): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $testResponse = $this->actingAs($user)->get(verificationUrlFor($user, $tampering));

    $testResponse->assertForbidden();

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
})->with([
    'an invalid email hash' => ['hash'],
    'a different user id' => ['id'],
    'an altered signature' => ['signature'],
    'an expired link' => ['expiry'],
]);

it('does not dispatch Verified again for an already verified user', function (): void {
    $user = createUser();

    Event::fake([Verified::class]);

    $verificationUrl = verificationUrlFor($user);

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertRedirect(route('home', absolute: false).'?verified=1');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();

    Event::assertNotDispatched(Verified::class);
});
