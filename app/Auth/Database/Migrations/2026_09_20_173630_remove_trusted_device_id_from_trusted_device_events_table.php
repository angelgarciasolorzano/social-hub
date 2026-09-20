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
            $blueprint->dropForeign(['trusted_device_id']);
            $blueprint->dropColumn('trusted_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_device_events', function (Blueprint $blueprint): void {
            $blueprint->foreignId('trusted_device_id')
                ->nullable()
                ->after('id');

            $blueprint->foreign('trusted_device_id')
                ->references('id')
                ->on('trusted_devices')
                ->nullOnDelete();
        });
    }
};
