<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console\Commands;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

#[Signature('admin:bootstrap-admin {email : Verified email of the existing user to promote}')]
#[Description('Promote the first verified user to the administrator roles.')]
class AdminBootstrapCommand extends Command
{
    private const string GUARD = 'web';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email === '') {
            $this->error('A valid email address is required.');

            return self::INVALID;
        }

        return DB::transaction(function () use ($email): int {
            $adminRole = Role::query()
                ->where('name', AdminRole::Admin->value)
                ->where('guard_name', self::GUARD)
                ->lockForUpdate()
                ->first();

            $usersReaderRole = Role::query()
                ->where('name', AdminRole::UsersReader->value)
                ->where('guard_name', self::GUARD)
                ->first();

            if (! $adminRole instanceof Role || ! $usersReaderRole instanceof Role) {
                $this->error('Admin roles are not initialized. Run the Admin authorization seeder first.');

                return self::FAILURE;
            }

            if (User::query()->permission(AdminPermission::AdminAccess->value)->exists()) {
                $this->error('An administrator with admin.access already exists.');

                return self::FAILURE;
            }

            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                $this->error("No user was found with the email {$email}.");

                return self::FAILURE;
            }

            if (! $user->hasVerifiedEmail()) {
                $this->error("The user {$email} must verify their email before promotion.");

                return self::FAILURE;
            }

            $user->assignRole($adminRole, $usersReaderRole);

            $this->info("Promoted {$email} to the admin and users-reader roles.");

            return self::SUCCESS;
        });
    }
}
