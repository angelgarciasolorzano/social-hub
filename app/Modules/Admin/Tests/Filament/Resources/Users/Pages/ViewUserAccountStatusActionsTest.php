<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use App\User\Modules\AccountStatus\Services\AccountStatusService;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

const VIEW_USER_SUSPENSION_REASON = 'Publicaciones de spam reiteradas';

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

/**
 * Sign in an account moderator on the admin guard.
 */
function actingAsAccountModerator(TestCase $testCase): User
{
    return AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);
}

/**
 * @return Testable<ViewUser>
 */
function viewUserPage(User $user): Testable
{
    return Livewire::test(ViewUser::class, ['record' => $user->id]);
}

/**
 * Open a header action's modal, fill in the reason and submit it.
 *
 * @param  Testable<ViewUser>  $testable
 * @return Testable<ViewUser>
 */
function submitAccountStatusAction(Testable $testable, string $actionName, string $reason): Testable
{
    return $testable
        ->call('mountAction', $actionName)
        ->set('mountedActions.0.data.reason', $reason)
        ->call('callMountedAction');
}

it('lets a moderator suspend an account with a reason', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = actingAsAccountModerator($testCase);
    $user = createUser();

    submitAccountStatusAction(viewUserPage($user)->assertSee('Suspend account'), 'suspendAccount', VIEW_USER_SUSPENSION_REASON)
        ->assertHasNoErrors();

    Notification::assertNotified('Account suspended');

    $accountStatusChange = AccountStatusChange::query()->sole();

    expect($user->refresh()->isSuspended())->toBeTrue()
        ->and($accountStatusChange->reason)->toBe(VIEW_USER_SUSPENSION_REASON)
        ->and($accountStatusChange->changed_by)->toBe($administrator->id)
        ->and(ActivityLog::query()->where('event', ActivityLogAdminEvent::AccountSuspended->value)->exists())->toBeTrue();
});

it('lets a moderator reactivate a suspended account with a reason', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsAccountModerator($testCase);
    $user = createSuspendedUser();

    $testable = viewUserPage($user)
        ->assertSee('Reactivate account')
        ->assertDontSee('Suspend account');

    submitAccountStatusAction($testable, 'reactivateAccount', 'Revisado con el usuario')
        ->assertHasNoErrors();

    Notification::assertNotified('Account reactivated');

    expect($user->refresh()->isSuspended())->toBeFalse();
});

it('requires a reason to suspend', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsAccountModerator($testCase);
    $user = createUser();

    submitAccountStatusAction(viewUserPage($user), 'suspendAccount', '')
        ->assertHasErrors(['mountedActions.0.data.reason' => 'required']);

    expect($user->refresh()->isSuspended())->toBeFalse()
        ->and(AccountStatusChange::query()->exists())->toBeFalse();
});

it('rejects a reason longer than the limit', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsAccountModerator($testCase);
    $user = createUser();

    submitAccountStatusAction(viewUserPage($user), 'suspendAccount', str_repeat('a', AccountStatusChange::REASON_MAX_LENGTH + 1))
        ->assertHasErrors(['mountedActions.0.data.reason' => 'max']);

    expect($user->refresh()->isSuspended())->toBeFalse();
});

it('hides both actions from an administrator without the moderation permissions and ignores a direct call', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
    $user = createUser();

    $testable = viewUserPage($user)
        ->assertDontSee('Suspend account')
        ->assertDontSee('Reactivate account');

    submitAccountStatusAction($testable, 'suspendAccount', VIEW_USER_SUSPENSION_REASON);

    expect($user->refresh()->isSuspended())->toBeFalse()
        ->and(AccountStatusChange::query()->exists())->toBeFalse();
});

it('does not offer suspension on the moderator own account', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $user = actingAsAccountModerator($testCase);

    viewUserPage($user)
        ->assertDontSee('Suspend account')
        ->assertDontSee('Reactivate account');
});

it('does not write a second suspension when the account was suspended from another tab with the modal open', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = actingAsAccountModerator($testCase);
    $user = createUser();
    $testable = viewUserPage($user)->call('mountAction', 'suspendAccount');

    resolve(AccountStatusService::class)->suspend(User::query()->findOrFail($user->id), $administrator, VIEW_USER_SUSPENSION_REASON);

    $testable
        ->set('mountedActions.0.data.reason', VIEW_USER_SUSPENSION_REASON)
        ->call('callMountedAction')
        ->assertOk()
        ->assertSet('mountedActions', []);

    Notification::assertNotified('The account was already suspended; reload the page to see its current status');

    expect(AccountStatusChange::query()->count())->toBe(1);
});

it('warns instead of writing when the account was reactivated from another tab with the modal open', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = actingAsAccountModerator($testCase);
    $user = createSuspendedUser();
    $testable = viewUserPage($user)->call('mountAction', 'reactivateAccount');
    $statusChangesBefore = AccountStatusChange::query()->count();

    resolve(AccountStatusService::class)->reactivate(User::query()->findOrFail($user->id), $administrator, 'Revisado con el usuario');

    $testable
        ->set('mountedActions.0.data.reason', 'Revisado con el usuario')
        ->call('callMountedAction')
        ->assertOk();

    Notification::assertNotified('The account was already active; reload the page to see its current status');

    expect(AccountStatusChange::query()->count())->toBe($statusChangesBefore + 1)
        ->and($user->refresh()->isSuspended())->toBeFalse();
});

it('warns in Spanish when the account was suspended from another tab with the modal open', function (): void {
    app()->setLocale('es');

    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = actingAsAccountModerator($testCase);
    $user = createUser();
    $testable = viewUserPage($user)->call('mountAction', 'suspendAccount');

    resolve(AccountStatusService::class)->suspend(User::query()->findOrFail($user->id), $administrator, VIEW_USER_SUSPENSION_REASON);

    $testable
        ->set('mountedActions.0.data.reason', VIEW_USER_SUSPENSION_REASON)
        ->call('callMountedAction');

    Notification::assertNotified('La cuenta ya estaba suspendida; recarga la página para ver su estado actual');
});

it('shows the suspension modal and notification in Spanish', function (): void {
    app()->setLocale('es');

    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsAccountModerator($testCase);
    $user = createUser();

    $testable = viewUserPage($user)
        ->assertSee('Suspender cuenta')
        ->call('mountAction', 'suspendAccount');

    $testable->__call('assertMountedActionModalSee', [[
        'Suspender cuenta',
        'La cuenta se cierra en todos lados',
        'Motivo',
        'Suspender',
    ]]);

    $testable
        ->set('mountedActions.0.data.reason', VIEW_USER_SUSPENSION_REASON)
        ->call('callMountedAction')
        ->assertHasNoErrors();

    Notification::assertNotified('Cuenta suspendida');

    expect(AccountStatusChange::query()->sole()->reason)->toBe(VIEW_USER_SUSPENSION_REASON);
});

it('shows the reactivation modal and notification in Spanish', function (): void {
    app()->setLocale('es');

    /** @var TestCase $testCase */
    $testCase = $this;
    actingAsAccountModerator($testCase);
    $user = createSuspendedUser();

    $testable = viewUserPage($user)
        ->assertSee('Reactivar cuenta')
        ->call('mountAction', 'reactivateAccount');

    $testable->__call('assertMountedActionModalSee', [[
        'Reactivar cuenta',
        'La cuenta puede volver a iniciar sesión.',
        'Motivo',
    ]]);

    submitAccountStatusAction($testable, 'reactivateAccount', 'Revisado con el usuario')
        ->assertHasNoErrors();

    Notification::assertNotified('Cuenta reactivada');
});
