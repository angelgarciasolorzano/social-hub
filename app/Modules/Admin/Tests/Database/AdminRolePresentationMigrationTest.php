<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Run or roll back only the migration that adds the presentation columns of a role.
 */
function runAdminRolePresentationMigration(string $command): void
{
    Artisan::call($command, [
        '--path' => __DIR__.'/../../Database/Migrations/2026_11_04_000000_add_presentation_columns_to_roles_table.php',
        '--realpath' => true,
    ]);
}

/**
 * Insert a role row as it was stored before the presentation columns existed.
 */
function insertRoleWithoutPresentation(string $name, string $guardName): int
{
    return DB::table('roles')->insertGetId([
        'name' => $name,
        'guard_name' => $guardName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('marks the catalog roles that already existed as system roles, and nothing else', function (): void {
    try {
        runAdminRolePresentationMigration('migrate:rollback');

        $adminRoleId = insertRoleWithoutPresentation(AdminRole::Admin->value, 'admin');
        $provisionerRoleId = insertRoleWithoutPresentation(AdminRole::AdminUsersProvisioner->value, 'admin');
        $otherAdminRoleId = insertRoleWithoutPresentation('support', 'admin');
        $webRoleId = insertRoleWithoutPresentation(AdminRole::Admin->value, 'web');

        runAdminRolePresentationMigration('migrate');

        $systemFlags = DB::table('roles')->pluck('is_system', 'id')->map(static fn (mixed $isSystem): bool => (bool) $isSystem);

        expect($systemFlags->get($adminRoleId))->toBeTrue()
            ->and($systemFlags->get($provisionerRoleId))->toBeTrue()
            ->and($systemFlags->get($otherAdminRoleId))->toBeFalse()
            ->and($systemFlags->get($webRoleId))->toBeFalse();
    } finally {
        Artisan::call('migrate:fresh');
    }
});
