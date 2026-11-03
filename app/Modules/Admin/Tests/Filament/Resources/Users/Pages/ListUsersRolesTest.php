<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The users list with every user on one page, filtered by the given roles.
 *
 * @param  list<AdminRole>  $adminRoles
 * @return Testable<ListUsers>
 */
function usersListFilteredByRoles(array $adminRoles): Testable
{
    return Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->set('tableRecordsPerPage', 50)
        ->set('tableFilters.admin_roles', [
            'values' => array_map(static fn (AdminRole $adminRole): string => $adminRole->value, $adminRoles),
        ]);
}

/**
 * Count the queries that rendering the first page of the list runs; call it once first so caches are warm.
 */
function countUsersListWithRolesQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListUsers::class)->call('loadTable');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('filters the users by one role or by the union of several', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminRolesReader);
    $moderator = createUser();
    $moderator->assignRole(AdminRole::AdminUsersModerator->value);

    $backupsOperator = createUser();
    $backupsOperator->assignRole(AdminRole::AdminBackupsOperator->value);
    createUser();

    $moderators = FilamentTableRecords::visibleRecordKeys(
        FilamentTableRecords::table(usersListFilteredByRoles([AdminRole::AdminUsersModerator])),
        User::class,
    );
    $moderatorsAndOperators = FilamentTableRecords::visibleRecordKeys(
        FilamentTableRecords::table(usersListFilteredByRoles([AdminRole::AdminUsersModerator, AdminRole::AdminBackupsOperator])),
        User::class,
    );

    expect($moderators)->toBe([FilamentTableRecords::recordKey($moderator)])
        ->and($moderatorsAndOperators)->toEqualCanonicalizing([
            FilamentTableRecords::recordKey($moderator),
            FilamentTableRecords::recordKey($backupsOperator),
        ]);
});

it('shows the admin roles of each user as badges in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminRolesReader);
    createUser()->assignRole(AdminRole::AdminBackupsOperator->value);

    usersListFilteredByRoles([AdminRole::AdminBackupsOperator])
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.users.column'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.admin_backups_operator.label'));
});

it('hides the roles column and filter from an administrator who cannot list roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
    createUser()->assignRole(AdminRole::AdminBackupsOperator->value);

    $table = FilamentTableRecords::table(Livewire::test(ListUsers::class)->call('loadTable'))->getTable();

    expect($table->getColumn('admin_roles')?->isVisible())->toBeFalse()
        ->and($table->getFilter('admin_roles'))->toBeNull();
});

it('does not add queries per row for the roles column', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader, AdminRole::AdminRolesReader);

    foreach (range(1, 2) as $index) {
        createUser()->assignRole(AdminRole::AdminPostsReader->value);
    }

    countUsersListWithRolesQueries();
    $queriesWithFewUsers = countUsersListWithRolesQueries();

    foreach (range(1, 6) as $index) {
        createUser()->assignRole(AdminRole::AdminPostsReader->value, AdminRole::AdminActivityReader->value);
    }

    countUsersListWithRolesQueries();

    expect(countUsersListWithRolesQueries())->toBe($queriesWithFewUsers);
});
