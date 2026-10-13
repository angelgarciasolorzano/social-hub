<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

final class AdminFilamentTestContext
{
    public static function configure(TestCase $testCase): void
    {
        $testCase->seed(AdminAuthorizationSeeder::class);

        Filament::setCurrentPanel('admin');
    }

    /**
     * Creates a user holding the given Admin roles and authenticates it on the admin guard.
     */
    public static function actingAsAdminWithRoles(TestCase $testCase, AdminRole ...$adminRoles): User
    {
        $user = createUser();

        $user->assignRole(array_map(
            static fn (AdminRole $adminRole): string => $adminRole->value,
            $adminRoles,
        ));

        $testCase->actingAs($user, 'admin');

        return $user;
    }
}
