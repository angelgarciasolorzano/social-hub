<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sample custom roles for local and testing databases, each with complete permission requirements and
 * held by a few random administrators (never Angel, who keeps only catalog roles). A role that already
 * exists is left as it is, so running the seeder twice changes nothing. It records no activity:
 * `ActivityLogSeeder` derives it from the stored roles.
 */
class AdminCustomRoleSeeder extends Seeder
{
    private const int MIN_HOLDERS = 1;

    private const int MAX_HOLDERS = 3;

    /**
     * @var list<array{label: string, description: string, permissions: list<AdminPermission>}>
     */
    private const array CUSTOM_ROLES = [
        [
            'label' => 'Soporte',
            'description' => 'Consulta los usuarios y su actividad para atender solicitudes.',
            'permissions' => [AdminPermission::AdminUsersView, AdminPermission::AdminActivityView],
        ],
        [
            'label' => 'Editor de contenido',
            'description' => 'Revisa publicaciones y redacta anuncios, sin borrar nada.',
            'permissions' => [
                AdminPermission::AdminPostsView,
                AdminPermission::AdminAnnouncementsCreate,
                AdminPermission::AdminAnnouncementsUpdate,
            ],
        ],
        [
            'label' => 'Auditor',
            'description' => 'Lee la actividad, el panel principal y los respaldos.',
            'permissions' => [
                AdminPermission::AdminActivityView,
                AdminPermission::AdminDashboardView,
                AdminPermission::AdminBackupsView,
            ],
        ],
    ];

    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! Role::query()->where('name', AdminRole::Admin->value)->where('guard_name', Role::GUARD_NAME)->exists()) {
            return;
        }

        foreach (self::CUSTOM_ROLES as $customRole) {
            $this->seedCustomRole($customRole['label'], $customRole['description'], $customRole['permissions']);
        }

        $permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @param  list<AdminPermission>  $adminPermissions
     */
    private function seedCustomRole(string $label, string $description, array $adminPermissions): void
    {
        $name = Role::CUSTOM_NAME_PREFIX.Str::slug($label);

        if (Role::query()->where('name', $name)->where('guard_name', Role::GUARD_NAME)->exists()) {
            return;
        }

        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => Role::GUARD_NAME,
            'is_system' => false,
            'label' => $label,
            'description' => $description,
        ]);

        $role->givePermissionTo(array_map(
            static fn (AdminPermission $adminPermission): string => $adminPermission->value,
            AdminPermission::withRequirements($adminPermissions),
        ));

        User::query()
            ->role(AdminRole::Admin->value, Role::GUARD_NAME)
            ->where('email', '!=', UserSeeder::TEST_USER_EMAIL)
            ->inRandomOrder()
            ->limit(random_int(self::MIN_HOLDERS, self::MAX_HOLDERS))
            ->get()
            ->each(static fn (User $user): User => $user->assignRole($role));
    }
}
