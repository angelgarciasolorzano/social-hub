<?php

declare(strict_types=1);

use App\User\Models\User;
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
        Schema::table('comments', function (Blueprint $blueprint): void {
            $blueprint->foreignIdFor(User::class, 'replied_to_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('replied_to_user_id');
        });
    }
};
