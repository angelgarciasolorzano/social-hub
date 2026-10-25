<?php

declare(strict_types=1);

namespace App\Modules\Post\Database\Seeders;

use App\Modules\Post\Database\Factories\PostFactory;
use App\Support\Seeding\SkewedRandom;
use App\User\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    private const int MAX_POSTS_PER_USER = 50;

    private const float POSTS_SKEW = 3.0;

    private const int WITH_IMAGE_SHARE_PERCENT = 60;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::all()->each(function (User $user): void {
            PostFactory::new()
                ->withImage(self::WITH_IMAGE_SHARE_PERCENT)
                ->count(SkewedRandom::count(0, self::MAX_POSTS_PER_USER, self::POSTS_SKEW))
                ->for($user)
                ->create();
        });
    }
}
