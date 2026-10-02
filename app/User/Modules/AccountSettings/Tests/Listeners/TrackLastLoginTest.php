<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

it('updates the last login timestamp after valid credentials authenticate the user', function (): void {
    config(['fortify.features' => []]);

    $user = createUser();
    $previousLoginAt = now()->subDays(1);
    $user->forceFill(['last_login_at' => $previousLoginAt])->save();

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $testResponse->assertRedirect(route('home', absolute: false));

    expect($user->refresh()->last_login_at?->greaterThan($previousLoginAt))->toBeTrue();
});

it('does not update the last login timestamp when credentials are invalid', function (): void {
    config(['fortify.features' => []]);

    $user = createUser();
    $previousLoginAt = now()->subDays(1);
    $user->forceFill(['last_login_at' => $previousLoginAt])->save();

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $testResponse->assertSessionHasErrors('email');
    $this->assertGuest();

    expect($user->refresh()->last_login_at?->toIso8601String())
        ->toBe($previousLoginAt->toIso8601String());
});

it('does not update the last login timestamp while the two-factor challenge is pending', function (): void {
    config([
        'fortify.features' => [Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ])],
    ]);

    $user = createUserWithTwoFactor();
    $previousLoginAt = now()->subDays(1);
    $user->forceFill(['last_login_at' => $previousLoginAt])->save();

    $testResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $testResponse->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);
    $this->assertGuest();

    expect($user->refresh()->last_login_at?->toIso8601String())
        ->toBe($previousLoginAt->toIso8601String());
});
