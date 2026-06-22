<?php

declare(strict_types=1);

namespace App\User\Seeders;

use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var Factory<User> $factory */
        $factory = User::factory();

        $factory->count(200)->create();

        $this->createTestUserIfNotExists();
    }

    protected function createTestUserIfNotExists(): void
    {
        $user = User::query()->firstOrCreate(['email' => 'angel@gmail.com'], [
            'name' => 'Angel Noé García Solórzano',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $testImageProfilePath = glob(app_path(User::TEST_USER_IMAGE_GLOB_PATH), GLOB_BRACE);
        $testImageCoverPath = glob(app_path(User::TEST_COVER_IMAGE_GLOB_PATH), GLOB_BRACE);

        if ($testImageProfilePath !== [] && $testImageProfilePath !== false && ($testImageCoverPath !== [] && $testImageCoverPath !== false)) {
            $mediaAdder = $user->addMedia($testImageProfilePath[0]);
            $mediaCoverAdder = $user->addMedia($testImageCoverPath[0]);

            if (app()->environment(['local', 'testing'])) {
                $mediaAdder->preservingOriginal();
                $mediaCoverAdder->preservingOriginal();
            }

            $mediaAdder->toMediaCollection(UserImageType::PROFILE_PICTURE->value());
            $mediaCoverAdder->toMediaCollection(UserImageType::COVER_IMAGE->value());
        }
    }
}
