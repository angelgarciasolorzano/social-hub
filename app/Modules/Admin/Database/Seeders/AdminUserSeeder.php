<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    private const string DEFAULT_ADMIN_EMAIL = 'angel@gmail.com';

    private const int PANEL_ADMIN_PERCENTAGE = 10;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $defaultAdmin = User::query()
            ->where('email', self::DEFAULT_ADMIN_EMAIL)
            ->firstOrFail();

        $defaultAdmin->syncRoles([
            AdminRole::Admin->value,
            AdminRole::UsersReader->value,
        ]);

        $builder = User::query()->where($defaultAdmin->getKeyName(), '!=', $defaultAdmin->getKey());

        (clone $builder)
            ->role(AdminRole::Admin->value)
            ->get()
            ->each(static function (User $user): void {
                $user->removeRole(AdminRole::UsersReader->value);
            });

        $otherUsersCount = (clone $builder)->count('*');

        if ($otherUsersCount === 0) {
            return;
        }

        $targetPanelAdminCount = (int) ceil($otherUsersCount * self::PANEL_ADMIN_PERCENTAGE / 100);

        $existingPanelAdminCount = (clone $builder)
            ->role(AdminRole::Admin->value)
            ->count('*');

        $additionalPanelAdminCount = max(0, $targetPanelAdminCount - $existingPanelAdminCount);

        if ($additionalPanelAdminCount === 0) {
            return;
        }

        $adminRole = Role::query()
            ->where('name', AdminRole::Admin->value)
            ->where('guard_name', 'web')
            ->firstOrFail();

        $builder
            ->whereDoesntHave('roles')
            ->whereDoesntHave('permissions')
            ->inRandomOrder()
            ->limit($additionalPanelAdminCount)
            ->get()
            ->each(static function (User $user) use ($adminRole): void {
                $user->assignRole($adminRole);
            });
    }
}
