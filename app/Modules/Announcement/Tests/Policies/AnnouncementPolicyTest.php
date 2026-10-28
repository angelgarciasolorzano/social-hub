<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Policies\AnnouncementPolicy;
use App\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * A user holding only the given Admin permission, through a role made for the test.
 */
function createUserWithAnnouncementPermission(AdminPermission $adminPermission): User
{
    $role = Role::findOrCreate('announcement-policy-test', 'admin');
    $role->givePermissionTo($adminPermission->value);

    $user = createUser();
    $user->assignRole($role);

    return $user;
}

/**
 * The announcement factory, typed for static analysis.
 */
function policyAnnouncementFactory(): AnnouncementFactory
{
    /** @var AnnouncementFactory $factory */
    $factory = Announcement::factory();

    return $factory;
}

beforeEach(function (): void {
    $this->seed(AdminAuthorizationSeeder::class);
});

it('resolves AnnouncementPolicy from the model attribute', function (): void {
    expect(Gate::getPolicyFor(Announcement::class))->toBeInstanceOf(AnnouncementPolicy::class);
});

it('allows each panel ability only with its own permission', function (string $ability, AdminPermission $adminPermission): void {
    $announcement = policyAnnouncementFactory()->createOne();
    $allowedUser = createUserWithAnnouncementPermission($adminPermission);
    $otherPermission = $adminPermission === AdminPermission::AdminAnnouncementsView
        ? AdminPermission::AdminAnnouncementsViewAny
        : AdminPermission::AdminAnnouncementsView;
    $deniedUser = createUser();
    grantAdminPermissionThroughTestRole($deniedUser, $otherPermission);

    $arguments = in_array($ability, ['viewAny', 'create'], true) ? Announcement::class : $announcement;

    expect(Gate::forUser($allowedUser)->allows($ability, $arguments))->toBeTrue()
        ->and(Gate::forUser($deniedUser)->allows($ability, $arguments))->toBeFalse();
})->with([
    'viewAny' => ['viewAny', AdminPermission::AdminAnnouncementsViewAny],
    'view' => ['view', AdminPermission::AdminAnnouncementsView],
    'create' => ['create', AdminPermission::AdminAnnouncementsCreate],
    'update' => ['update', AdminPermission::AdminAnnouncementsUpdate],
    'replicate' => ['replicate', AdminPermission::AdminAnnouncementsCreate],
    'delete' => ['delete', AdminPermission::AdminAnnouncementsDelete],
]);

it('denies bulk deletion and restoring even to the announcements manager', function (string $ability): void {
    $user = createUser();
    $user->assignRole(AdminRole::AdminAnnouncementsManager->value);

    $announcement = policyAnnouncementFactory()->createOne();

    $arguments = str_ends_with($ability, 'Any') ? Announcement::class : $announcement;

    expect(Gate::forUser($user)->allows($ability, $arguments))->toBeFalse();
})->with(['deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny']);

it('lets a user dismiss a dismissible announcement shown to them, even twice', function (): void {
    $user = createUser();
    $announcement = policyAnnouncementFactory()->createOne();

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeTrue();

    $announcement->dismissedBy()->attach($user, ['created_at' => now()]);

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeTrue();
});

it('denies dismissing an announcement that is not dismissible or not shown to the user', function (Closure $makeAnnouncement): void {
    $user = createUserWithTwoFactor();
    /** @var Announcement $announcement */
    $announcement = $makeAnnouncement();

    expect(Gate::forUser($user)->allows('dismiss', $announcement))->toBeFalse();
})->with([
    'not dismissible' => [fn (): Announcement => policyAnnouncementFactory()->notDismissible()->createOne()],
    'expired' => [fn (): Announcement => policyAnnouncementFactory()->expired()->createOne()],
    'scheduled' => [fn (): Announcement => policyAnnouncementFactory()->scheduled()->createOne()],
    'for another audience' => [fn (): Announcement => policyAnnouncementFactory()->forAudience(AnnouncementAudience::WithoutTwoFactor)->createOne()],
]);
