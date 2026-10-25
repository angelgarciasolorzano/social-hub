<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\AccountStatus;

it('ends the panel session of an administrator suspended while signed in', function (): void {
    AdminFilamentTestContext::configure($this);

    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin);
    $user->saveAppAuthenticationSecret('filament-test-secret');
    $user->forceFill(['status' => AccountStatus::Suspended])->save();

    $this->get('/admin')
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHasErrors(['email' => AccountStatus::SUSPENDED_LOGIN_MESSAGE]);

    $this->assertGuest('admin');
});
