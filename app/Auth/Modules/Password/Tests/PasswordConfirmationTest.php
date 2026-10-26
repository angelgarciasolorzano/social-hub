<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the password confirmation screen', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->get(route('password.confirm'));

    $testResponse->assertOk();

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('auth/password/ConfirmPassword')
    );
});

it('redirects guests to login when they open the password confirmation screen', function (): void {
    $testResponse = $this->get(route('password.confirm'));

    $testResponse->assertRedirect(route('login'));
});

it('returns to the intended page when password confirmation is still valid', function (): void {
    $user = createUser();
    $intendedUrl = route('setting.security.two-factor-authentication.index', absolute: false);

    $testResponse = $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => Date::now()->getTimestamp(),
            'auth.password_confirmation_redirect' => $intendedUrl,
        ])
        ->get(route('password.confirm'));

    $testResponse->assertRedirect($intendedUrl);
});
