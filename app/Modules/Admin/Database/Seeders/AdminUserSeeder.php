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

    private const int ADMIN_SHARE_PERCENT = 20;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()
            ->where('email', self::DEFAULT_ADMIN_EMAIL)
            ->firstOrFail();

        $user->assignRole(
            $this->roleFor(AdminRole::Admin),
            ...array_map($this->roleFor(...), $this->readerRoles()),
        );

        $this->promoteRandomAdmins();
    }

    /**
     * Promote random users until at least the configured share of all users
     * is an administrator. Each one gets the base role plus a random,
     * non-empty subset of the reader roles; existing roles are kept.
     */
    private function promoteRandomAdmins(): void
    {
        $requiredAdmins = intdiv(User::query()->count('*') * self::ADMIN_SHARE_PERCENT + 99, 100);
        $currentAdmins = User::query()->role(AdminRole::Admin->value, self::GUARD)->count();

        $missingAdmins = $requiredAdmins - $currentAdmins;

        if ($missingAdmins <= 0) {
            return;
        }

        $readerRoles = collect($this->readerRoles())->map($this->roleFor(...));

        User::query()
            ->withoutRole(AdminRole::Admin->value, self::GUARD)
            ->inRandomOrder()
            ->limit($missingAdmins)
            ->get()
            ->each(function (User $user) use ($readerRoles): void {
                $chosenReaderRoles = $readerRoles->shuffle()->take(random_int(1, $readerRoles->count()))->all();

                $user->assignRole($this->roleFor(AdminRole::Admin), ...$chosenReaderRoles);
            });
    }

    /**
     * @return list<AdminRole>
     */
    private function readerRoles(): array
    {
        return [
            AdminRole::AdminDashboardReader,
            AdminRole::AdminUsersReader,
            AdminRole::AdminPostsReader,
            AdminRole::AdminActivityReader,
        ];
    }

    private function roleFor(AdminRole $adminRole): Role
    {
        return Role::query()
            ->where('name', $adminRole->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();
    }
}
