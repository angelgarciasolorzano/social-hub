<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

it('returns a zero trusted-device count without querying devices while two-factor is disabled', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUser()->refresh();
    createTrustedDevice($user);

    $trustedDeviceCountQueries = [];

    DB::listen(function (QueryExecuted $queryExecuted) use (&$trustedDeviceCountQueries): void {
        $normalizedSql = strtolower($queryExecuted->sql);

        if (str_contains($normalizedSql, 'trusted_devices') && str_contains($normalizedSql, 'count')) {
            $trustedDeviceCountQueries[] = $queryExecuted->sql;
        }
    });

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->where('twoFactorEnabled', false)
            ->where('trustedDevicesCount', 0)
        );

    expect($trustedDeviceCountQueries)->toBeEmpty();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('counts active devices and includes revoked devices in the recent optional list', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUserWithTwoFactor();
    createTrustedDevice($user, [
        'name' => 'Most recently used',
        'last_used_at' => Date::now()->subMinutes(1),
    ]);
    $revokedTrustedDevice = createTrustedDevice($user, [
        'name' => 'Recently revoked',
        'last_used_at' => Date::now()->subMinutes(2),
    ]);
    $revokedTrustedDevice->delete();
    createTrustedDevice($user, [
        'name' => 'Older active device',
        'last_used_at' => Date::now()->subMinutes(3),
    ]);
    createTrustedDevice($user, [
        'name' => 'Oldest active device',
        'last_used_at' => Date::now()->subMinutes(4),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->where('twoFactorEnabled', true)
            ->where('trustedDevicesCount', 3)
            ->missing('trustedDevices')
            ->reloadOnly('trustedDevices', fn (Assert $assert): Assert => $assert
                ->has('trustedDevices', 3)
                ->where('trustedDevices.0.name', 'Most recently used')
                ->where('trustedDevices.1.id', $revokedTrustedDevice->id)
                ->where('trustedDevices.1.name', 'Recently revoked')
                ->where('trustedDevices.1.deletedAt', $revokedTrustedDevice->deleted_at?->toIso8601String())
            )
        );
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('returns an empty recent trusted-device list when no devices exist', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUserWithTwoFactor();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->missing('trustedDevices')
            ->reloadOnly('trustedDevices', fn (Assert $assert): Assert => $assert
                ->where('trustedDevices', [])
            )
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
