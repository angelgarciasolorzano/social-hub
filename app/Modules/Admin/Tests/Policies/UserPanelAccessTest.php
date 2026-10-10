<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Factories\UserFactory;
use Filament\Panel;

it('allows verified users with admin.access into the admin panel', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('denies regular users without admin.access from the admin panel', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('denies unverified users even when they have admin.access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = UserFactory::new()->unverified()->createOne();
    $user->assignRole(AdminRole::Admin->value);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('denies admin users from panel ids other than admin', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    expect($user->canAccessPanel(Panel::make()->id('user-facing')))->toBeFalse();
});
