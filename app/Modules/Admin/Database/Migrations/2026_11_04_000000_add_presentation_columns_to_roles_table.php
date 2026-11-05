<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The values are written here instead of read from `Role` and `AdminRole`, so the migration keeps doing
 * the same thing when those classes change later.
 */
return new class extends Migration
{
    private const string GUARD_NAME = 'admin';

    private const int LABEL_MAX_LENGTH = 80;

    private const int DESCRIPTION_MAX_LENGTH = 255;

    /**
     * The catalog roles that existed when custom roles arrived (SOC-70). Roles added to the catalog later
     * are created as system roles by `AdminAuthorizationSeeder`.
     *
     * @var list<string>
     */
    private const array SYSTEM_ROLE_NAMES = [
        'admin',
        'admin-users-reader',
        'admin-users-provisioner',
        'admin-users-moderator',
        'admin-posts-reader',
        'admin-dashboard-reader',
        'admin-activity-reader',
        'admin-backups-reader',
        'admin-backups-operator',
        'admin-announcements-manager',
        'admin-announcements-reviewer',
        'admin-roles-reader',
        'admin-roles-manager',
    ];

    /**
     * Run the migrations. The catalog roles that already exist become system roles here, so they are never
     * treated as custom roles (deletable, renamable) while the authorization seeder has not run again.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $blueprint): void {
            $blueprint->boolean('is_system')->default(false)->index()->after('guard_name');

            $blueprint->string('label', self::LABEL_MAX_LENGTH)->nullable()->after('is_system');

            $blueprint->string('description', self::DESCRIPTION_MAX_LENGTH)->nullable()->after('label');
        });

        DB::table('roles')
            ->where('guard_name', self::GUARD_NAME)
            ->whereIn('name', self::SYSTEM_ROLE_NAMES)
            ->update(['is_system' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['is_system']);
            $blueprint->dropColumn(['is_system', 'label', 'description']);
        });
    }
};
