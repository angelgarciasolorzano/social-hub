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
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->string('phone')->nullable();
            $blueprint->string('preferred_locale', 2)->default('en');
            $blueprint->string('biography', 160)->nullable();
            $blueprint->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'phone',
                'preferred_locale',
                'biography',
                'last_login_at',
            ]);
        });
    }
};
