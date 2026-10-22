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
        Schema::create('activity_log', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('log_name')->nullable();
            $blueprint->text('description');
            $blueprint->nullableMorphs('subject', 'subject');
            $blueprint->string('event')->nullable();
            $blueprint->nullableMorphs('causer', 'causer');
            $blueprint->json('attribute_changes')->nullable();
            $blueprint->json('properties')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['log_name', 'created_at'], 'activity_log_log_name_created_at_index');
            $blueprint->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
