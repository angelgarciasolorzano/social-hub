<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Open the manage roles modal of the user, select the given roles and submit it with the password.
 *
 * @param  list<AdminRole>  $adminRoles
 * @return Testable<ViewUser>
 */
function submitManageRoles(User $user, array $adminRoles, string $currentPassword = 'password'): Testable
{
    return Livewire::test(ViewUser::class, ['record' => $user->id])
        ->call('mountAction', 'manageRoles')
        ->set('mountedActions.0.data.roles', array_map(static fn (AdminRole $adminRole): string => $adminRole->value, $adminRoles))
        ->set('mountedActions.0.data.current_password', $currentPassword)
        ->call('callMountedAction');
}

/**
 * The Admin role names of the user, sorted.
 *
 * @return array<int, mixed>
 */
function managedUserRoleNames(User $user): array
{
    return User::query()->findOrFail($user->id)->getRoleNames()->sort()->values()->all();
}

function actingAsUserRolesManager(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager);
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('lets a roles manager assign and remove the roles of another user', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = actingAsUserRolesManager($testCase);
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    submitManageRoles($user, [AdminRole::AdminPostsReader])->assertHasNoErrors();

    Notification::assertNotified(trans_choice('admin/roles.actions.manage_roles.success', 2, ['count' => 2]));

    $activityLogs = ActivityLog::query()->where('log_name', 'admin')->get();

    expect(managedUserRoleNames($user))->toBe([AdminRole::AdminPostsReader->value])
        ->and($activityLogs->pluck('event')->sort()->values()->all())->toBe([
            ActivityLogAdminEvent::RoleGranted->value,
            ActivityLogAdminEvent::RoleRevoked->value,
        ])
        ->and($activityLogs->pluck('causer_id')->unique()->all())->toBe([$administrator->id]);
});

it('opens the modal with the current cataloged roles of the user', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value, AdminRole::Admin->value);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->call('mountAction', 'manageRoles')
        ->assertSet('mountedActions.0.data.roles', [AdminRole::Admin->value, AdminRole::AdminPostsReader->value]);
});

it('reports when nothing changed and records nothing', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    $user = createUser();
    $user->assignRole(AdminRole::AdminPostsReader->value);

    submitManageRoles($user, [AdminRole::AdminPostsReader])->assertHasNoErrors();

    Notification::assertNotified(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.manage_roles.unchanged'));

    expect(ActivityLog::query()->where('log_name', 'admin')->exists())->toBeFalse();
});

it('changes nothing with a wrong password', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    $user = createUser();

    submitManageRoles($user, [AdminRole::AdminBackupsOperator], 'wrong-password')
        ->assertHasErrors(['mountedActions.0.data.current_password']);

    expect(managedUserRoleNames($user))
        ->toBeEmpty();
});

it('does not offer the action on the manager own account', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = actingAsUserRolesManager($testCase);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.manage_roles.label'));

    submitManageRoles($user, [AdminRole::Admin, AdminRole::AdminRolesManager, AdminRole::AdminBackupsOperator]);

    expect(managedUserRoleNames($user))->toBe([AdminRole::Admin->value, AdminRole::AdminRolesManager->value]);
});

it('hides the action from a moderator and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);
    $user = createUser();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertDontSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.manage_roles.label'));

    submitManageRoles($user, [AdminRole::AdminBackupsOperator]);

    expect(managedUserRoleNames($user))
        ->toBeEmpty();
});

it('keeps the account status actions working next to it', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesManager, AdminRole::AdminUsersModerator);

    Livewire::test(ViewUser::class, ['record' => createUser()->id])
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.actions.manage_roles.label'))
        ->assertSee(translatedTextIn(PreferredLocale::ENGLISH, 'admin/users.actions.suspend.label'));
});

it('shows the modal in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    $user = createUser();
    $user->forceFill(['name' => 'Ada Lovelace'])->save();

    $testable = Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSee('Gestionar roles')
        ->call('mountAction', 'manageRoles');

    $testable->__call('assertMountedActionModalSee', [[
        'Gestionar los roles de Ada Lovelace',
        'Moderador de usuarios',
        'Suspender y reactivar cuentas desde el detalle del usuario.',
        'Contraseña actual',
        'Guardar roles',
    ]]);
});

it('assigns a custom role like a system role, with its label in the modal', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    $customRole = RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte', 'description' => 'Atiende a los usuarios.']);
    $user = createUser();

    $testable = Livewire::test(ViewUser::class, ['record' => $user->id])->call('mountAction', 'manageRoles');
    $testable->__call('assertMountedActionModalSee', [['Soporte', 'Atiende a los usuarios.']]);

    $testable
        ->set('mountedActions.0.data.roles', [$customRole->name])
        ->set('mountedActions.0.data.current_password', 'password')
        ->call('callMountedAction')
        ->assertHasNoErrors();

    expect(managedUserRoleNames($user))->toBe(['custom-soporte'])
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::RoleGranted->value)->sole()->properties?->get('role'))
        ->toBe('custom-soporte');
});

it('summarizes the roles to assign and remove before saving', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsUserRolesManager($testCase);
    RoleFactory::new()->createOne(['name' => 'custom-soporte', 'label' => 'Soporte']);
    $user = createUser();
    $user->assignRole(AdminRole::AdminUsersReader->value);

    $testable = Livewire::test(ViewUser::class, ['record' => $user->id])->call('mountAction', 'manageRoles');
    $testable->__call('assertMountedActionModalSee', [[TranslatedText::of('admin/roles.actions.manage_roles.summary_none')]]);

    $testable->set('mountedActions.0.data.roles', ['custom-soporte']);
    $testable->__call('assertMountedActionModalSee', [[
        trans_choice('admin/roles.actions.manage_roles.summary_grant', 1, ['count' => 1, 'list' => 'Soporte']),
        trans_choice('admin/roles.actions.manage_roles.summary_revoke', 1, [
            'count' => 1,
            'list' => translatedTextIn(PreferredLocale::ENGLISH, 'admin/roles.catalog.roles.admin_users_reader.label'),
        ]),
    ]]);

    expect(managedUserRoleNames($user))->toBe([AdminRole::AdminUsersReader->value]);
});
