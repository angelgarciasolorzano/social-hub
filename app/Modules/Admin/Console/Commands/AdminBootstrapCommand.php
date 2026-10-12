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
    private const string GUARD = 'admin';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email === '') {
            $this->error('A valid email address is required.');

            return self::INVALID;
        }

        return DB::transaction(function () use ($email): int {
            $requiredRoleNames = [
                AdminRole::Admin->value,
                AdminRole::AdminDashboardReader->value,
                AdminRole::AdminUsersReader->value,
                AdminRole::AdminPostsReader->value,
            ];

            $roles = Role::query()
                ->whereIn('name', $requiredRoleNames)
                ->where('guard_name', self::GUARD)
                ->lockForUpdate()
                ->get()
                ->keyBy('name');

            if ($roles->count() !== count($requiredRoleNames)) {
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

            $user->assignRole($roles->all());

            $this->info("Promoted {$email} to these Admin roles: ".implode(', ', $requiredRoleNames).'.');

            return self::SUCCESS;
        });
    }
}
