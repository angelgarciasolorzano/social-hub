<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the approval state and append-only review history.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $blueprint): void {
            $blueprint->string('status', 20)->default('draft');
        });

        Schema::create('announcement_reviews', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $blueprint->string('action', 20);
            $blueprint->text('comment')->nullable();
            $blueprint->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_reviews');

        Schema::table('announcements', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('status');
        });
    }
};
