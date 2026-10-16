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
        Schema::create('admin_invitations', function (Blueprint $blueprint): void {
            $blueprint->id();

            $blueprint->string('email')->index();

            $blueprint->foreignId('invited_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $blueprint->foreignId('target_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $blueprint->char('token_hash', 64)->unique();

            $blueprint->timestamp('expires_at');

            $blueprint->timestamp('accepted_at')->nullable();

            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
