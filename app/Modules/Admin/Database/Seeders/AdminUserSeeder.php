<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    private const string GUARD = 'admin';

    private const string DEFAULT_ADMIN_EMAIL = 'angel@gmail.com';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()
            ->where('email', self::DEFAULT_ADMIN_EMAIL)
            ->firstOrFail();

        $adminRole = Role::query()
            ->where('name', AdminRole::Admin->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $dashboardReaderRole = Role::query()
            ->where('name', AdminRole::AdminDashboardReader->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $usersReaderRole = Role::query()
            ->where('name', AdminRole::AdminUsersReader->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $postsReaderRole = Role::query()
            ->where('name', AdminRole::AdminPostsReader->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $user->assignRole($adminRole, $dashboardReaderRole, $usersReaderRole, $postsReaderRole);
    }
}
