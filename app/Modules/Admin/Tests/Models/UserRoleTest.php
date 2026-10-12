<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('assigns admin guard roles and resolves their permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);
    $user->refresh();

    $this->assertDatabaseMissing('roles', [
        'name' => AdminRole::AdminUsersReader->value,
        'guard_name' => 'web',
    ]);
    $this->assertDatabaseMissing('permissions', [
        'name' => AdminPermission::AdminUsersViewAny->value,
        'guard_name' => 'web',
    ]);

    expect($user->hasRole(AdminRole::AdminUsersReader->value, 'admin'))->toBeTrue()
        ->and($user->hasPermissionTo(AdminPermission::AdminUsersViewAny->value, 'admin'))->toBeTrue();
});

it('omits authentication secrets from array and json serialization', function (): void {
    $user = new User;
    $user->forceFill([
        'password' => 'password-secret',
        'remember_token' => 'remember-token-secret',
        'two_factor_secret' => 'totp-secret',
        'two_factor_recovery_codes' => 'recovery-codes-secret',
    ]);

    $hiddenAttributes = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @var array<string, mixed> $jsonRepresentation */
    $jsonRepresentation = json_decode($user->toJson(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_intersect($hiddenAttributes, array_keys($user->toArray())))->toBeEmpty()
        ->and(array_intersect($hiddenAttributes, array_keys($jsonRepresentation)))->toBeEmpty();
});

it('does not assign roles or permissions through mass assignment', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    foreach ([
        'role' => AdminRole::Admin->value,
        'roles' => [AdminRole::Admin->value],
        'permissions' => [AdminPermission::AdminAccess->value],
    ] as $attribute => $value) {
        expect(fn () => $user->fill([$attribute => $value]))
            ->toThrow(MassAssignmentException::class);
    }

    $user->refresh();

    expect($user->getRoleNames())->toBeEmpty()
        ->and($user->getPermissionNames())->toBeEmpty()
        ->and(array_intersect(['role', 'roles', 'permissions'], array_keys($user->getAttributes())))
        ->toBeEmpty();
});
