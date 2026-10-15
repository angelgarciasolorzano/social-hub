<?php

declare(strict_types=1);

namespace App\User\Database\Seeders;

use App\User\Database\Factories\UserFactory;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use App\User\Modules\TwoFactor\Enums\TwoFactorStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const int DEFAULT_USER_COUNT = 200;

    private const int CONFIRMED_TWO_FACTOR_SHARE_PERCENT = 25;

    private const int PENDING_TWO_FACTOR_SHARE_PERCENT = 5;

    public const string TEST_USER_EMAIL = 'angel@gmail.com';

    public function __construct(private readonly int $userCount = self::DEFAULT_USER_COUNT) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->shuffledTwoFactorStatuses() as $twoFactorStatus) {
            $this->userFactoryFor($twoFactorStatus)->create();
        }

        $this->createTestUserIfNotExists();
    }

    /**
     * One status per user to create, with fixed shares of confirmed and
     * pending two-factor users (rounded down) in random order. The rest, and
     * the test user created afterwards, have no two-factor authentication.
     *
     * @return list<TwoFactorStatus>
     */
    private function shuffledTwoFactorStatuses(): array
    {
        $confirmedCount = intdiv($this->userCount * self::CONFIRMED_TWO_FACTOR_SHARE_PERCENT, 100);
        $pendingCount = intdiv($this->userCount * self::PENDING_TWO_FACTOR_SHARE_PERCENT, 100);

        $twoFactorStatuses = [
            ...array_fill(0, $confirmedCount, TwoFactorStatus::Confirmed),
            ...array_fill(0, $pendingCount, TwoFactorStatus::Pending),
            ...array_fill(0, $this->userCount - $confirmedCount - $pendingCount, TwoFactorStatus::Disabled),
        ];

        shuffle($twoFactorStatuses);

        return $twoFactorStatuses;
    }

    private function userFactoryFor(TwoFactorStatus $twoFactorStatus): UserFactory
    {
        return match ($twoFactorStatus) {
            TwoFactorStatus::Confirmed => UserFactory::new()->twoFactorConfirmed(),
            TwoFactorStatus::Pending => UserFactory::new()->twoFactorPending(),
            TwoFactorStatus::Disabled => UserFactory::new(),
        };
    }

    protected function createTestUserIfNotExists(): void
    {
        $user = User::query()->where('email', self::TEST_USER_EMAIL)->first() ?? User::query()->forceCreate([
            'email' => self::TEST_USER_EMAIL,
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
