<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = 'role_id';
        $pivotPermission = 'permission_id';

        /**
         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.
         */
        Schema::create($tableNames['permissions'], static function (Blueprint $blueprint): void {
            $blueprint->id(); // permission id
            $blueprint->string('name');
            $blueprint->string('guard_name');
            $blueprint->timestamps();

            $blueprint->unique(['name', 'guard_name']);
        });

        /**
         * See `docs/prerequisites.md` for suggested lengths on 'name' and 'guard_name' if "1071 Specified key was too long" errors are encountered.
         */
        Schema::create($tableNames['roles'], static function (Blueprint $blueprint): void {
            $blueprint->id(); // role id
            $blueprint->string('name');
            $blueprint->string('guard_name');
            $blueprint->timestamps();
            $blueprint->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $blueprint) use ($tableNames, $columnNames, $pivotPermission): void {
            $blueprint->unsignedBigInteger($pivotPermission);

            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger($columnNames['model_morph_key']);
            $blueprint->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $blueprint->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();
            $blueprint->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $blueprint) use ($tableNames, $columnNames, $pivotRole): void {
            $blueprint->unsignedBigInteger($pivotRole);

            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger($columnNames['model_morph_key']);
            $blueprint->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $blueprint->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->cascadeOnDelete();
            $blueprint->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $blueprint) use ($tableNames, $pivotRole, $pivotPermission): void {
            $blueprint->unsignedBigInteger($pivotPermission);
            $blueprint->unsignedBigInteger($pivotRole);

            $blueprint->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $blueprint->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $blueprint->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        cache()->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
