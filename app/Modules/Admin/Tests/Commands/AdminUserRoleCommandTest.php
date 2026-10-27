<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;

function runAdminUserRole(string $action, string $email, string $role, ?User $user = null): int
{
    $arguments = [
        'action' => $action,
        'email' => $email,
        'role' => $role,
    ];

    if ($user instanceof User) {
        $arguments['--actor'] = $user->email;
    }

    return Artisan::call('admin:user-role', $arguments);
}

/**
 * The `admin` activities recorded for the given event.
 *
 * @return Collection<int, Activity>
 */
function adminRoleActivities(string $eventName): Collection
{
    return Activity::query()->where('log_name', 'admin')->where('event', $eventName)->get();
}

it('grants a catalog role and exposes its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::AdminUsersReader->value, createAdminCommandActor());
    $commandOutput = Artisan::output();
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)->toContain("Role admin-users-reader is assigned to {$user->email}.")
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersView->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('grants the announcements manager role and its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::AdminAnnouncementsManager->value, createAdminCommandActor());
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::AdminAnnouncementsManager->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAnnouncementsCreate->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAnnouncementsDelete->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeFalse();
});

it('grants panel entry through the admin role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::Admin->value, createAdminCommandActor());
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('revokes only the selected role and its permissions', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::AdminUsersReader->value, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminUsersViewAny->value))->toBeFalse();
});

it('rejects an unknown role without changing existing assignments', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $exitCode = runAdminUserRole('grant', $user->email, 'unlisted-role', $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Unknown Admin role "unlisted-role".')
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole('unlisted-role'))->toBeFalse()
        ->and(Role::query()->where('name', 'unlisted-role')->exists())->toBeFalse();
});

it('rejects actions other than grant or revoke without mutation', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('replace', $user->email, AdminRole::Admin->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('Action must be "grant" or "revoke".')
        ->and($user->roles()->exists())->toBeFalse();
});

it('rejects an email that does not belong to an existing user', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runAdminUserRole('grant', 'missing@example.com', AdminRole::Admin->value, createAdminCommandActor());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('No user was found with the email missing@example.com.')
        ->and(User::query()->where('email', 'missing@example.com')->exists())->toBeFalse();
});

it('prevents revoking admin.access from the last administrator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::Admin->value, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('would leave no user with admin.access')
        ->and($user->hasRole(AdminRole::Admin->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking admin.access from one user when another administrator remains', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $targetUser = createUser();
    $targetUser->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $targetUser->email, AdminRole::Admin->value, $user);
    $user->refresh();
    $targetUser->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($targetUser->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($targetUser->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($targetUser->can(AdminPermission::AdminAccess->value))->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('allows revoking one role when the user retains admin.access through another role', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Role::query()
        ->where('name', AdminRole::AdminUsersReader->value)
        ->where('guard_name', 'admin')
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::AdminAccess->value);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value, AdminRole::AdminUsersReader->value);

    $exitCode = runAdminUserRole('revoke', $user->email, AdminRole::Admin->value, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($user->hasRole(AdminRole::Admin->value))->toBeFalse()
        ->and($user->hasRole(AdminRole::AdminUsersReader->value))->toBeTrue()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('rejects role changes until the authorization catalog is seeded', function (): void {
    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::Admin->value, $user);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin authorization is not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
});

it('records who granted the role and to whom', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $actor = createAdminCommandActor();
    $user = createUser();

    runAdminUserRole('grant', $user->email, AdminRole::AdminPostsReader->value, $actor);

    $activity = adminRoleActivities('admin.role_granted')->sole();

    expect($activity)
        ->subject_type->toBe('user')
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($actor->id)
        ->and($activity->properties?->get('role'))->toBe(AdminRole::AdminPostsReader->value);
});

it('records who revoked the role and from whom', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $actor = createAdminCommandActor();
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    runAdminUserRole('revoke', $user->email, AdminRole::AdminUsersReader->value, $actor);

    expect(adminRoleActivities('admin.role_revoked')->sole())
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($actor->id);
});

it('does not record granting a role the user already has', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    runAdminUserRole('grant', $user->email, AdminRole::AdminUsersReader->value, createAdminCommandActor());

    expect(adminRoleActivities('admin.role_granted'))->toBeEmpty();
});

it('does not record a revocation blocked to protect the last administrator', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createAdminCommandActor();

    runAdminUserRole('revoke', $user->email, AdminRole::Admin->value, $user);

    expect(adminRoleActivities('admin.role_revoked'))->toBeEmpty();
});

it('rejects the command without --actor and changes nothing', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runAdminUserRole('grant', $user->email, AdminRole::AdminUsersReader->value);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('The --actor option is required')
        ->and($user->roles()->exists())->toBeFalse()
        ->and(Activity::query()->exists())->toBeFalse();
});

it('rejects an actor that cannot be found, is unverified or lacks admin.access', function (string $actorKind, string $expectedMessage): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $actorEmail = match ($actorKind) {
        'missing' => 'ghost@example.com',
        'unverified' => tap(createUser(), function (User $user): void {
            $user->assignRole(AdminRole::Admin->value);
            $user->forceFill(['email_verified_at' => null])->save();
        })->email,
        default => createUser()->email,
    };

    $exitCode = Artisan::call('admin:user-role', [
        'action' => 'grant',
        'email' => $user->email,
        'role' => AdminRole::AdminUsersReader->value,
        '--actor' => $actorEmail,
    ]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain($expectedMessage)
        ->and($user->roles()->exists())->toBeFalse()
        ->and(Activity::query()->exists())->toBeFalse();
})->with([
    'missing actor' => ['missing', 'No actor was found with the email ghost@example.com.'],
    'unverified actor' => ['unverified', 'must have a verified email'],
    'actor without admin.access' => ['without access', 'must have admin.access'],
]);
