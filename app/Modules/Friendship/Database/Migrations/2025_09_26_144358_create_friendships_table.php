<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The friendships of the application: one row per pair of users, and the declined requests.
 *
 * `user_low_id` and `user_high_id` are virtual generated columns: MySQL rejects a cascading
 * foreign key on the base column of a stored generated column, while an indexed virtual one
 * keeps both the unique pair and `cascadeOnDelete` of `requester_id` and `receiver_id`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $blueprint->string('status')->default('pending');
            $blueprint->timestamp('accepted_at')->nullable();
            $blueprint->timestamps();
            $blueprint->unsignedBigInteger('user_low_id')->virtualAs('LEAST(requester_id, receiver_id)');
            $blueprint->unsignedBigInteger('user_high_id')->virtualAs('GREATEST(requester_id, receiver_id)');

            $blueprint->unique(['user_low_id', 'user_high_id']);
            $blueprint->index(['receiver_id', 'status']);
            $blueprint->index(['requester_id', 'status']);
        });

        Schema::create('friend_request_declines', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $blueprint->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $blueprint->timestamp('declined_at');

            $blueprint->unique(['requester_id', 'receiver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_request_declines');
        Schema::dropIfExists('friendships');
    }
};
