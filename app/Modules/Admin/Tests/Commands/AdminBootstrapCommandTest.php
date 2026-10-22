<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;

function runBootstrapAdmin(string $email, ?User $user = null): int
{
    $arguments = ['email' => $email];

    if ($user instanceof User) {
        $arguments['--actor'] = $user->email;
    }

    return Artisan::call('admin:bootstrap-admin', $arguments);
}

it('promotes a verified user to the base and all reader roles', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runBootstrapAdmin($user->email, $user);
    $commandOutput = Artisan::output();
    $bootstrappedUser = $user->refresh();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($commandOutput)->toContain("Promoted {$user->email}")
        ->and($bootstrappedUser->hasAllRoles([
            AdminRole::Admin->value,
            AdminRole::AdminDashboardReader->value,
            AdminRole::AdminUsersReader->value,
            AdminRole::AdminPostsReader->value,
            AdminRole::AdminActivityReader->value,
        ]))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminAccess->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminDashboardView->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminUsersViewAny->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminUsersView->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminPostsViewAny->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminPostsView->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminActivityViewAny->value))->toBeTrue()
        ->and($bootstrappedUser->can(AdminPermission::AdminActivityView->value))->toBeTrue()
        ->and($bootstrappedUser->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('rejects an email that does not belong to an existing user', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $exitCode = runBootstrapAdmin('missing@example.com', createUser());

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('No user was found with the email missing@example.com.')
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('rejects a user whose email has not been verified', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = UserFactory::new()->unverified()->createOne();

    $exitCode = runBootstrapAdmin($user->email, createUser());
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('must verify their email before promotion')
        ->and($user->roles()->exists())->toBeFalse()
        ->and(User::query()->permission(AdminPermission::AdminAccess->value)->exists())->toBeFalse();
});

it('refuses to bootstrap when a user already has admin.access', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $user->assignRole(AdminRole::Admin->value);

    $candidate = createUser();

    $exitCode = runBootstrapAdmin($candidate->email, $candidate);
    $candidate->refresh();
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('An administrator with admin.access already exists.')
        ->and($candidate->roles()->exists())->toBeFalse()
        ->and(Activity::query()->exists())->toBeFalse()
        ->and($user->can(AdminPermission::AdminAccess->value))->toBeTrue();
});

it('requires the Admin authorization catalog to be seeded first', function (): void {
    $user = createUser();

    $exitCode = runBootstrapAdmin($user->email, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin roles are not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
});

it('requires every bootstrap role to exist in the Admin guard', function (AdminRole $missingAdminRole): void {
    $this->seed(AdminAuthorizationSeeder::class);

    Role::query()
        ->where('name', $missingAdminRole->value)
        ->where('guard_name', 'admin')
        ->delete();

    $user = createUser();

    $exitCode = runBootstrapAdmin($user->email, $user);
    $user->refresh();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Admin roles are not initialized.')
        ->and($user->roles()->exists())->toBeFalse();
})->with([
    'posts reader' => [AdminRole::AdminPostsReader],
    'activity reader' => [AdminRole::AdminActivityReader],
]);

it('records the bootstrap with the actor and the assigned roles', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $actor = createUser();
    $user = createUser();

    runBootstrapAdmin($user->email, $actor);

    $activity = Activity::query()->where('event', 'admin.bootstrapped')->sole();

    expect($activity)
        ->log_name->toBe('admin')
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($actor->id)
        ->and($activity->properties?->get('roles'))->toBe([
            AdminRole::Admin->value,
            AdminRole::AdminDashboardReader->value,
            AdminRole::AdminUsersReader->value,
            AdminRole::AdminPostsReader->value,
            AdminRole::AdminActivityReader->value,
        ]);
});

it('accepts the promoted user as their own actor', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    runBootstrapAdmin($user->email, $user);

    expect(Activity::query()->where('event', 'admin.bootstrapped')->sole())
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($user->id);
});

it('rejects the command without --actor and changes nothing', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();

    $exitCode = runBootstrapAdmin($user->email);

    expect($exitCode)->toBe(Command::INVALID)
        ->and(Artisan::output())->toContain('The --actor option is required')
        ->and($user->roles()->exists())->toBeFalse();
});

it('rejects an unverified actor and changes nothing', function (): void {
    $this->seed(AdminAuthorizationSeeder::class);

    $user = createUser();
    $actor = UserFactory::new()->unverified()->createOne();

    $exitCode = runBootstrapAdmin($user->email, $actor);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('must have a verified email')
        ->and($user->roles()->exists())->toBeFalse()
        ->and(Activity::query()->exists())->toBeFalse();
});
