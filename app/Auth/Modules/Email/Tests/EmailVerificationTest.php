<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

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

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertRedirect(route('home', absolute: false).'?verified=1');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatchedOnce(Verified::class);
});

it('rejects a signed verification URL with an invalid email hash', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('different-email@example.test')],
    );

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertForbidden();

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

it('rejects a signed verification URL for a different user id', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id + 1, 'hash' => sha1($user->email)],
    );

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertForbidden();

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

it('rejects a verification URL with an altered signature', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );
    $alteredVerificationUrl = str_replace('signature=', 'signature=altered-', $verificationUrl);

    $testResponse = $this->actingAs($user)->get($alteredVerificationUrl);

    $testResponse->assertForbidden();

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

it('rejects an expired signed verification URL', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Event::fake([Verified::class]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertForbidden();

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

it('does not dispatch Verified again for an already verified user', function (): void {
    $user = createUser();

    Event::fake([Verified::class]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $testResponse = $this->actingAs($user)->get($verificationUrl);

    $testResponse->assertRedirect(route('home', absolute: false).'?verified=1');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();

    Event::assertNotDispatched(Verified::class);
});
