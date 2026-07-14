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

            $blueprint->string('ip', 45)->nullable();

            $blueprint->timestamp('last_used_at')->nullable();

            $blueprint->timestamp('expires_at')->index();

            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
