<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string UNIQUE_INDEX = 'likes_user_id_likeable_type_likeable_id_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('likes', function (Blueprint $blueprint): void {
            $blueprint->unique(['user_id', 'likeable_type', 'likeable_id'], self::UNIQUE_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('likes', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(self::UNIQUE_INDEX);
        });
    }
};
