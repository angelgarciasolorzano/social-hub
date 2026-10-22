<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Admin\Enums\AdminRole;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\TestCase;

/**
 * Create Angel plus other users, then seed the admin roles and the admin users.
 */
function seedAdminsForUsers(TestCase $testCase, int $userCount): User
{
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();
    $userFactory->count($userCount - 1)->create();

    $testCase->seed(AdminAuthorizationSeeder::class);
    $testCase->seed(AdminUserSeeder::class);

    return $user;
}

/**
 * @return list<string>
 */
function readerRoleNames(): array
{
    return [
        AdminRole::AdminDashboardReader->value,
        AdminRole::AdminUsersReader->value,
        AdminRole::AdminPostsReader->value,
        AdminRole::AdminActivityReader->value,
        AdminRole::AdminBackupsReader->value,
    ];
}

/**
 * @return Collection<int, User>
 */
function usersWithAdminRole(): Collection
{
    return User::query()->role(AdminRole::Admin->value, 'admin')->get();
}

it('makes the expected share of the users administrators', function (int $userCount, int $expectedAdmins): void {
    seedAdminsForUsers($this, $userCount);

    expect(usersWithAdminRole())->toHaveCount($expectedAdmins);
})->with([
    'one user' => [1, 1],
    'five users' => [5, 1],
    'six users, rounded up' => [6, 2],
    'thirty users' => [30, 6],
    'thirty-one users, rounded up' => [31, 7],
]);

it('gives Angel the base role and every reader role', function (): void {
    $user = seedAdminsForUsers($this, 10);

    expect($user->refresh()->getRoleNames()->sort()->values()->all())
        ->toBe(collect([AdminRole::Admin->value, ...readerRoleNames()])->sort()->values()->all());
});

it('gives every random administrator the base role and between one and four reader roles', function (): void {
    $user = seedAdminsForUsers($this, 30);

    $randomAdmins = usersWithAdminRole()->reject(fn (User $admin): bool => $admin->is($user));

    expect($randomAdmins)->toHaveCount(5);

    foreach ($randomAdmins as $randomAdmin) {
        $readerRoles = $randomAdmin->getRoleNames()->reject(fn (string $roleName): bool => $roleName === AdminRole::Admin->value);

        expect($readerRoles->count())->toBeBetween(1, 4)
            ->and($readerRoles->diff(readerRoleNames()))->toBeEmpty();
    }
});

it('gives different reader roles to different random administrators', function (): void {
    $user = seedAdminsForUsers($this, 60);

    $readerRoleSets = usersWithAdminRole()
        ->reject(fn (User $admin): bool => $admin->is($user))
        ->map(fn (User $user): string => $user->getRoleNames()->sort()->implode(','));

    expect($readerRoleSets->unique()->count())->toBeGreaterThan(1);
});

it('does not give roles to users who are not administrators', function (): void {
    seedAdminsForUsers($this, 30);

    $usersWithoutAdminRole = User::query()->withoutRole(AdminRole::Admin->value, 'admin')->get();

    expect($usersWithoutAdminRole)->toHaveCount(24)
        ->and($usersWithoutAdminRole->filter(fn (User $user): bool => $user->roles()->exists()))->toBeEmpty();
});

it('does not add administrators when it runs twice', function (): void {
    seedAdminsForUsers($this, 30);
    $adminIdsAfterFirstRun = usersWithAdminRole()->modelKeys();

    $this->seed(AdminUserSeeder::class);

    expect(usersWithAdminRole()->modelKeys())->toBe($adminIdsAfterFirstRun);
});

it('does not promote anyone outside the local and testing environments', function (): void {
    $user = createUser();
    $user->forceFill(['email' => 'angel@gmail.com'])->save();

    /** @var Factory<User> $userFactory */
    $userFactory = User::factory();
    $userFactory->count(9)->create();

    $this->seed(AdminAuthorizationSeeder::class);
    $this->app->detectEnvironment(fn (): string => 'production');

    resolve(AdminUserSeeder::class)->run();

    expect(usersWithAdminRole())->toBeEmpty();
});
