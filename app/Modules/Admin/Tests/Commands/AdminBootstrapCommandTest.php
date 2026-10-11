<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('promotes a verified user to both bootstrap roles', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = Artisan::call('admin:bootstrap-admin', ['email' => $user->email]);
    $commandOutput = Artisan::output();
    $bootstrappedUser = $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)->toContain("Promoted {$user->email}")
        ->and($bootstrappedUser->hasAllRoles([
            AdminRole::Admin->value,
            AdminRole::UsersReader->value,
        ]))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::UsersViewAny->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::UsersView->value))->toBeTrue();
});

it('rejects an email that does not belong to an existing user', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = Artisan::call('admin:bootstrap-admin', ['email' => 'missing@example.com']);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('No user was found with the email missing@example.com.')
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('rejects a user whose email has not been verified', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = UserFactory::new()->unverified()->createOne();

    $exitCode = Artisan::call('admin:bootstrap-admin', ['email' => $user->email]);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('must verify their email before promotion')
        ->and($user->roles()->exists())->toBeFalse()
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('refuses to bootstrap when a user already has admin.access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $candidate = createUser();

    $exitCode = Artisan::call('admin:bootstrap-admin', ['email' => $candidate->email]);
    $candidate->refresh();
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('An administrator with admin.access already exists.')
        ->and($candidate->roles()->exists())->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('requires the Admin authorization catalog to be seeded first', function (): void {
    $user = createUser();

    $exitCode = Artisan::call('admin:bootstrap-admin', ['email' => $user->email]);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin roles are not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
});
