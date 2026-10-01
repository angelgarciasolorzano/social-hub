<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('redirects guests to login when they request a verification email', function (): void {
    $this->post(route('verification.send'))
        ->assertRedirect(route('login'));
});

it('resends the verification notification to an unverified user', function (): void {
    $user = createUser();
    $user->forceFill(['email_verified_at' => null])->save();

    Notification::fake();

    $testResponse = $this->from(route('verification.notice', absolute: false))
        ->actingAs($user)
        ->post(route('verification.send'));

    $testResponse
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend a verification notification to an already verified user', function (): void {
    $user = createUser();

    Notification::fake();

    $testResponse = $this->actingAs($user)->post(route('verification.send'));

    $testResponse->assertRedirect(route('home', absolute: false));
    Notification::assertNotSentTo($user, VerifyEmail::class);
});
