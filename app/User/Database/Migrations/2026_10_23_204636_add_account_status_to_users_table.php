<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The default value marks every existing account as active.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->string('status', 20)->default('active')->index();
        });

        Schema::create('account_status_changes', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $blueprint->string('status', 20);
            $blueprint->text('reason');
            $blueprint->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->timestamp('created_at')->nullable();

            $blueprint->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_status_changes');

        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['status']);
            $blueprint->dropColumn('status');
        });
    }
};
