<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use Illuminate\Database\Eloquent\MassAssignmentException;

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
