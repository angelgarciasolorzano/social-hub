<?php

namespace App\User\Seeders;

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
        User::query()->firstOrCreate(['email' => 'angel@gmail.com'], [
            'name' => 'Angel Noé García Solórzano',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
