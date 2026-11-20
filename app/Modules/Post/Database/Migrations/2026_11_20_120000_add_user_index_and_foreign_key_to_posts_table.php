<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string USER_INDEX = 'posts_user_id_id_index';

    private const string USER_FOREIGN_KEY = 'posts_user_id_foreign';

    /**
     * Index the posts by author and tie each one to its user, so deleting a user deletes its posts.
     *
     * @throws RuntimeException When a post belongs to a user that no longer exists.
     */
    public function up(): void
    {
        $orphanPostsCount = DB::table('posts')
            ->leftJoin('users', 'users.id', '=', 'posts.user_id')
            ->whereNull('users.id')
            ->count();

        if ($orphanPostsCount > 0) {
            throw new RuntimeException(sprintf(
                'Cannot add the user foreign key to posts: %d post(s) belong to a user that no longer exists. Delete them, with their likes, comments and images, before migrating.',
                $orphanPostsCount,
            ));
        }

        Schema::table('posts', function (Blueprint $blueprint): void {
            $blueprint->index(['user_id', 'id'], self::USER_INDEX);

            $blueprint->foreign('user_id', self::USER_FOREIGN_KEY)
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(self::USER_FOREIGN_KEY);
            $blueprint->dropIndex(self::USER_INDEX);
        });
    }
};
