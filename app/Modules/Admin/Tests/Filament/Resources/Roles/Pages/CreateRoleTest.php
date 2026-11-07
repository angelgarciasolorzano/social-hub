<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\CreateRole;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sign in an administrator who may create roles.
 */
function actingAsRoleCreator(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
}

/**
 * The create role page.
 *
 * @return Testable<CreateRole>
 */
function createRolePage(): Testable
{
    return Livewire::test(CreateRole::class);
}

/**
 * The custom roles that exist.
 */
function customRoleCount(): int
{
    return Role::query()->where('is_system', false)->count();
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('creates a custom role with its permissions and their requirements, and opens it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = actingAsRoleCreator($testCase);

    $testable = createRolePage()
        ->set('data.label', 'Soporte')
        ->set('data.description', 'Atiende a los usuarios.')
        ->set('data.permissions.users', [AdminPermission::AdminUsersView->value])
        ->assertSet('data.permissions.users', [AdminPermission::AdminUsersViewAny->value, AdminPermission::AdminUsersView->value])
        ->set('data.current_password', 'password')
        ->call('create');

    $role = Role::query()->where('is_system', false)->sole();

    $testable->assertHasNoErrors();
    $testable->assertRedirect(RoleResource::getUrl('view', ['record' => $role]));

    expect($role->name)->toBe('custom-soporte')
        ->and($role->label)->toBe('Soporte')
        ->and($role->description)->toBe('Atiende a los usuarios.')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe([
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersViewAny->value,
        ])
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleCreated->value)->sole()->causer_id)->toBe($user->id);
});

it('starts with no permissions checked', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleCreator($testCase);

    createRolePage()
        ->assertSet('data.permissions.users', [])
        ->assertSet('data.permissions.roles', []);
});

it('copies the permissions of an existing role and lets them change before saving', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleCreator($testCase);

    createRolePage()
        ->set('data.start_from', AdminRole::AdminPostsReader->value)
        ->assertSet('data.permissions.posts', [AdminPermission::AdminPostsViewAny->value, AdminPermission::AdminPostsView->value])
        ->set('data.permissions.posts', [AdminPermission::AdminPostsViewAny->value])
        ->set('data.label', 'Moderación')
        ->set('data.current_password', 'password')
        ->call('create')
        ->assertHasNoErrors();

    $role = Role::query()->where('is_system', false)->sole();

    expect($role->permissions->pluck('name')->all())->toBe([AdminPermission::AdminPostsViewAny->value])
        ->and(Role::query()->where('name', AdminRole::AdminPostsReader->value)->sole()->permissions)->toHaveCount(2);
});

it('does not leave the first step with an empty or taken label', function (string $label): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleCreator($testCase);
    RoleFactory::new()->createOne(['label' => 'Soporte']);

    $testable = createRolePage()->set('data.label', $label);
    $testable->__call('goToNextWizardStep', []);
    $testable->__call('assertWizardCurrentStep', [1]);
    $testable->assertHasErrors(['data.label']);
})->with(['empty' => [''], 'taken ignoring case' => ['  SOPORTE ']]);

it('reports a label taken while the wizard was open on its field and creates nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleCreator($testCase);

    $testable = createRolePage()
        ->set('data.label', 'Soporte')
        ->set('data.current_password', 'password');

    RoleFactory::new()->createOne(['label' => 'Soporte']);

    $testable->call('create')
        ->assertHasErrors(['data.label' => AdminAuthorizationFailure::RoleLabelTaken->message()]);

    expect(customRoleCount())->toBe(1)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleCreated->value)->exists())->toBeFalse();
});

it('creates nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsRoleCreator($testCase);

    createRolePage()
        ->set('data.label', 'Soporte')
        ->set('data.current_password', 'wrong-password')
        ->call('create')
        ->assertHasErrors(['data.current_password']);

    expect(customRoleCount())->toBe(0);
});

it('denies the page to a roles reader', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    createRolePage()->assertForbidden();
});
