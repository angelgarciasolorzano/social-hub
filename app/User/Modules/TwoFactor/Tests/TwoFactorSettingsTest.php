<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

it('renders the two-factor settings page', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUser()->refresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()
            ->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->where('twoFactorEnabled', false)
        );
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('redirects to password confirmation when required', function (): void {
    $user = createUser()->refresh();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->actingAs($user)
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertRedirect(route('password.confirm'));
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('renders settings without password confirmation when it is disabled', function (): void {
    $user = createUser()->refresh();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
        );
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('reports management as unavailable when Fortify disables two-factor authentication', function (): void {
    config(['fortify.features' => []]);

    $user = createUser()->refresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()
            ->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->where('canManageTwoFactor', false)
        );
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);
