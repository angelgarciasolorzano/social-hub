<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Support;

use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;

final class RegistrationDashboardTestData
{
    public static function createDashboardRegistration(string $createdAt, bool $verified = true): User
    {
        $user = createUser();

        $user->forceFill([
            'created_at' => $createdAt,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user;
    }

    public static function createDashboardReader(): User
    {
        $user = createUser();

        $user->assignRole([
            AdminRole::Admin->value,
            AdminRole::AdminDashboardReader->value,
        ]);

        $user->forceFill(['created_at' => '2020-01-01 00:00:00'])->save();

        return $user;
    }
}
