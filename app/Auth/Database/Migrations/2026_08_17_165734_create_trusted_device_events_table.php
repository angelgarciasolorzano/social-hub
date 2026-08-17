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
        Schema::create('trusted_device_events', function (Blueprint $blueprint): void {
            $blueprint->id();

            $blueprint->foreignId('trusted_device_id')
                ->nullable()
                ->constrained('trusted_devices')
                ->nullOnDelete();

            $blueprint->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $blueprint->string('action');

            $blueprint->string('ip', 45)->nullable();

            $blueprint->string('user_agent', 255)->nullable();

            $blueprint->timestamps();

            $blueprint->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_device_events');
    }
};
