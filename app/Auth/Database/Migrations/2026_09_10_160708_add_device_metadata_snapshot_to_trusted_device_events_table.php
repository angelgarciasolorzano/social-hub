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
        Schema::table('trusted_device_events', function (Blueprint $blueprint): void {
            $blueprint->boolean('device_is_mobile')->nullable()->after('device_label');
            $blueprint->string('device_os_name', 100)->nullable()->after('device_is_mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_device_events', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['device_is_mobile', 'device_os_name']);
        });
    }
};
