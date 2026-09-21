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
        Schema::create('trusted_devices', function (Blueprint $blueprint): void {
            $blueprint->id();

            $blueprint->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $blueprint->string('token_hash', 64)->unique();

            $blueprint->string('name')->nullable();

            $blueprint->string('user_agent', 255)->nullable();

            $blueprint->string('browser')->nullable();

            $blueprint->string('browser_version')->nullable();

            $blueprint->string('os_name')->nullable();

            $blueprint->string('os_version')->nullable();

            $blueprint->boolean('is_mobile')->default(false);

            $blueprint->string('ip', 45)->nullable();

            $blueprint->timestamp('last_used_at')->nullable();

            $blueprint->timestamp('expires_at')->index();

            $blueprint->softDeletes();

            $blueprint->timestamps();
        });

        Schema::create('trusted_device_events', function (Blueprint $blueprint): void {
            $blueprint->id();

            $blueprint->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $blueprint->string('action');

            $blueprint->string('device_label')->nullable();

            $blueprint->boolean('device_is_mobile')->nullable();

            $blueprint->string('device_os_name', 100)->nullable();

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
        Schema::dropIfExists('trusted_devices');
    }
};
