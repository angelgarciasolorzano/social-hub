<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The default value shows every existing announcement as a toast, as before.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $blueprint): void {
            $blueprint->string('display', 20)->default('toast')->after('audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('display');
        });
    }
};
