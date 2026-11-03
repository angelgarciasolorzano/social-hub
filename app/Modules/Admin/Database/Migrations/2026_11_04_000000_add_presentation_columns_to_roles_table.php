<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Role;
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
        Schema::table('roles', function (Blueprint $blueprint): void {
            $blueprint->boolean('is_system')->default(false)->index()->after('guard_name');

            $blueprint->string('label', Role::LABEL_MAX_LENGTH)->nullable()->after('is_system');

            $blueprint->string('description', Role::DESCRIPTION_MAX_LENGTH)->nullable()->after('label');
        });
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
