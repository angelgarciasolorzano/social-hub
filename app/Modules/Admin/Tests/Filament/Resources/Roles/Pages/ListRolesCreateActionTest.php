<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminAuthorizationFailure;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ListRoles;
use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Open the create role modal on the roles list.
 *
 * @return Testable<ListRoles>
 */
function openCreateRoleModal(): Testable
{
    return Livewire::test(ListRoles::class)->call('mountAction', 'createRole');
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('creates a custom role with the requirements checked live and opens it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $testable = openCreateRoleModal()
        ->set('mountedActions.0.data.label', 'Soporte')
        ->set('mountedActions.0.data.description', 'Atiende a los usuarios.')
        ->set('mountedActions.0.data.permissions.users', [AdminPermission::AdminUsersView->value])
        ->assertSet('mountedActions.0.data.permissions.users', [
            AdminPermission::AdminUsersViewAny->value,
            AdminPermission::AdminUsersView->value,
        ])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    $role = Role::query()->custom()->sole();

    $testable->assertHasNoErrors();
    $testable->assertRedirect(RoleResource::getUrl('view', ['record' => $role]));

    $activityLog = ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleCreated->value)->sole();

    expect($role->name)->toBe('custom-soporte')
        ->and($role->label)->toBe('Soporte')
        ->and($role->description)->toBe('Atiende a los usuarios.')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe([
            AdminPermission::AdminUsersView->value,
            AdminPermission::AdminUsersViewAny->value,
        ])
        ->and($activityLog->causer_id)->toBe($user->id);

    $listPage = Livewire::test(ListRoles::class);
    $listPage->__call('searchTable', ['Soporte']);
    $listPage->__call('assertCanSeeTableRecords', [[$role]]);
    $listPage->assertSee(TranslatedText::of('admin/roles.types.custom'));
});

it('starts the modal with no permissions and a summary that nothing changes', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    $testable = openCreateRoleModal()->assertSet('mountedActions.0.data.permissions.users', []);

    $testable->__call('assertMountedActionModalSee', [[TranslatedText::of('admin/roles.summary.none')]]);
});

it('reports a taken label on its field and creates nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
    RoleFactory::new()->createOne(['label' => 'Soporte']);

    openCreateRoleModal()
        ->set('mountedActions.0.data.label', 'SOPORTE')
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.label' => AdminAuthorizationFailure::RoleLabelTaken->message()]);

    expect(Role::query()->custom()->count())->toBe(1)
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('creates nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);

    openCreateRoleModal()
        ->set('mountedActions.0.data.label', 'Soporte')
        ->set('mountedActions.0.data.current_password', 'wrong-password')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect(Role::query()->custom()->exists())->toBeFalse();
});

it('hides the action from a roles reader and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(ListRoles::class)
        ->assertDontSee(TranslatedText::of('admin/roles.actions.create.label'));

    openCreateRoleModal()
        ->set('mountedActions.0.data.label', 'Soporte')
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction');

    expect(Role::query()->custom()->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});
