<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Tests\TestCase;

/**
 * Create an administrator who can enter the panel and sign them in on the admin guard.
 */
function signInPanelAdministrator(TestCase $testCase): User
{
    $testCase->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);
    $user->saveAppAuthenticationSecret('filament-test-secret');

    $testCase->actingAs($user, 'admin');

    return $user;
}

it('ends the panel session of an administrator suspended while signed in', function (): void {
    $user = signInPanelAdministrator($this);
    $user->forceFill(['status' => AccountStatus::Suspended])->save();

    $this->get('/admin')
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest('admin');
});

it('keeps the panel session of an active administrator', function (): void {
    $user = signInPanelAdministrator($this);

    $this->get('/admin')->assertOk();

    $this->assertAuthenticatedAs($user, 'admin');
});
