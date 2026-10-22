<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Database\Seeders\TrustedDeviceSeeder;
use App\Modules\ActivityLog\Database\Seeders\ActivityLogSeeder;
use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Comment\Database\Seeders\CommentSeeder;
use App\Modules\Like\Database\Seeders\LikeSeeder;
use App\Modules\Post\Database\Seeders\PostSeeder;
use App\User\Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AdminAuthorizationSeeder::class,
            AdminUserSeeder::class,
            TrustedDeviceSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            LikeSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
