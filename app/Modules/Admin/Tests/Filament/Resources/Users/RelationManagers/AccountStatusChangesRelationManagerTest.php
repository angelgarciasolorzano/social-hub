<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\RelationManagers\AccountStatusChangesRelationManager;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Database\Factories\AccountStatusChangeFactory;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

/**
 * @return Testable<Component>
 */
function accountStatusChangesRelationManagerFor(User $user): Testable
{
    return Livewire::test(AccountStatusChangesRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

/**
 * Create a status change of the given account, made by the given administrator at the given time.
 */
function createStatusHistoryEntry(User $user, ?User $administrator, string $createdAt, bool $isSuspension = true): AccountStatusChange
{
    /** @var AccountStatusChangeFactory $factory */
    $factory = AccountStatusChange::factory();
    $factory = $isSuspension ? $factory->suspended() : $factory->reactivated();

    return $factory->for($user)->createOne([
        'changed_by' => $administrator?->id,
        'created_at' => $createdAt,
    ]);
}

it('loads account status history lazily and displays a loading skeleton', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $table = FilamentTableRecords::table(accountStatusChangesRelationManagerFor(createUser()))->getTable();

    expect(AccountStatusChangesRelationManager::isLazy())->toBeTrue()
        ->and($table->isLoadingDeferred())->toBeFalse()
        ->and($table->hasLoadingSkeleton())->toBeTrue();
});

it('lists the status changes of the user, most recent first, with reason and administrator', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    $administrator = AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
    $user = createUser();
    $accountStatusChange = createStatusHistoryEntry($user, $administrator, '2026-10-01 10:00:00');
    $reactivation = createStatusHistoryEntry($user, $administrator, '2026-10-05 10:00:00', isSuspension: false);
    createStatusHistoryEntry(createUser(), $administrator, '2026-10-06 10:00:00');

    $testable = accountStatusChangesRelationManagerFor($user)
        ->assertSee($accountStatusChange->reason)
        ->assertSee($administrator->name);
    $table = FilamentTableRecords::table($testable);

    expect(FilamentTableRecords::visibleRecordKeys($table, AccountStatusChange::class))
        ->toBe([
            FilamentTableRecords::recordKey($reactivation),
            FilamentTableRecords::recordKey($accountStatusChange),
        ]);
});

it('shows a deleted administrator as a deleted user', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
    $user = createUser();
    createStatusHistoryEntry($user, null, '2026-10-01 10:00:00');

    accountStatusChangesRelationManagerFor($user)
        ->assertOk()
        ->assertSee('Deleted user');
});

it('hides the tab from an administrator who cannot view users', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminPostsReader);
    $user = createUser();

    expect(AccountStatusChangesRelationManager::canViewForRecord($user, ViewUser::class))->toBeFalse();

    Livewire::test(AccountStatusChangesRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ])->assertForbidden();
});

it('offers no create, edit or delete actions', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersModerator);
    $user = createUser();
    createStatusHistoryEntry($user, null, '2026-10-01 10:00:00');

    $table = FilamentTableRecords::table(accountStatusChangesRelationManagerFor($user))->getTable();

    expect($table->getHeaderActions())
        ->toBeEmpty()
        ->and($table->getRecordActions())
        ->toBeEmpty()
        ->and($table->getToolbarActions())
        ->toBeEmpty();
});
