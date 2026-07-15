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
        Schema::table('trusted_devices', function (Blueprint $blueprint): void {
            $blueprint->string('browser')->nullable()->after('user_agent');
            $blueprint->string('os_name')->nullable()->after('browser');
            $blueprint->string('os_version')->nullable()->after('os_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['browser', 'os_name', 'os_version']);
        });
    }
};