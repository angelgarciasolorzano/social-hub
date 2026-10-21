<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;

it('redirects guests to the admin login page', function (): void {
    $this->get('/admin')
        ->assertRedirect('/admin/login')
        ->assertSessionHas('url.intended');
});

it('allows authorized administrators to enter the admin panel', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);
    $user->saveAppAuthenticationSecret('filament-test-secret');

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertOk();
});

it('denies authenticated users without admin access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertForbidden();
});

it('does not expose a public admin registration route', function (): void {
    $this->get('/admin/register')
        ->assertNotFound();
});
