<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Open the two-factor settings page as the given user, with password confirmation already granted.
 *
 * @return TestResponse<Response>
 */
function getTwoFactorSettingsAs(TestCase $testCase, User $user): TestResponse
{
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    return $testCase->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'));
}

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

it('prioritizes non-revoked devices and includes revoked devices in the recent optional list', function (): void {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = createUserWithTwoFactor();
    $mostRecentlyRevokedTrustedDevice = createTrustedDevice($user, [
        'name' => 'Most recently revoked',
        'last_used_at' => Date::now()->subMinutes(1),
    ]);
    $mostRecentlyRevokedTrustedDevice->delete();

    createTrustedDevice($user, [
        'name' => 'Second recently revoked',
        'last_used_at' => Date::now()->subMinutes(2),
    ])->delete();

    createTrustedDevice($user, [
        'name' => 'Third recently revoked',
        'last_used_at' => Date::now()->subMinutes(3),
    ])->delete();

    $trustedDevice = createTrustedDevice($user, [
        'name' => 'Most recently used active device',
        'last_used_at' => Date::now()->subMinutes(4),
    ]);
    $secondMostRecentlyUsedActiveDevice = createTrustedDevice($user, [
        'name' => 'Second most recently used active device',
        'last_used_at' => Date::now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('setting/modules/twoFactor/TwoFactor')
            ->where('twoFactorEnabled', true)
            ->where('trustedDevicesCount', 2)
            ->missing('trustedDevices')
            ->reload(
                callback: fn (Assert $assert): Assert => $assert->missing('firstTrustedDevice'),
                only: 'firstTrustedDevice',
            )
            ->reloadOnly('trustedDevices', fn (Assert $assert): Assert => $assert
                ->has('trustedDevices', 3)
                ->where('trustedDevices.0.id', $trustedDevice->id)
                ->where('trustedDevices.0.name', 'Most recently used active device')
                ->where('trustedDevices.1.id', $secondMostRecentlyUsedActiveDevice->id)
                ->where('trustedDevices.1.name', 'Second most recently used active device')
                ->where('trustedDevices.2.id', $mostRecentlyRevokedTrustedDevice->id)
                ->where('trustedDevices.2.name', 'Most recently revoked')
                ->where('trustedDevices.2.deletedAt', $mostRecentlyRevokedTrustedDevice->deleted_at?->toIso8601String())
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

it('reports the two-factor status as disabled when the user has no secret', function (): void {
    getTwoFactorSettingsAs($this, createUser()->refresh())
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('twoFactorStatus', 'disabled'));
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('reports the two-factor status as pending when the secret is not confirmed', function (): void {
    $user = createUserWithTwoFactor();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    getTwoFactorSettingsAs($this, $user)
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('twoFactorStatus', 'pending'));
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('reports the two-factor status as confirmed when the secret is confirmed', function (): void {
    getTwoFactorSettingsAs($this, createUserWithTwoFactor())
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('twoFactorStatus', 'confirmed'));
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('does not send the two-factor status when management is unavailable', function (): void {
    config(['fortify.features' => []]);

    $this->actingAs(createUser()->refresh())
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('setting.security.two-factor-authentication.index'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorStatus')
        );
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);
