<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. `body` stores the rich editor HTML, which is sanitized when rendered.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('title', 120);
            $blueprint->text('body');
            $blueprint->string('type', 20);
            $blueprint->string('audience', 30);
            $blueprint->timestamp('starts_at');
            $blueprint->timestamp('ends_at')->nullable();
            $blueprint->string('button_label', 40)->nullable();
            $blueprint->string('button_url', 2048)->nullable();
            $blueprint->boolean('is_dismissible')->default(true);
            $blueprint->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->timestamps();

            $blueprint->index(['starts_at', 'ends_at']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $blueprint->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->timestamp('created_at')->nullable();

            $blueprint->unique(['announcement_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
