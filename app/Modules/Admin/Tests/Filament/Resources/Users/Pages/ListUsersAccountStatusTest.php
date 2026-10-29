<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\FilamentTableRecords;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);
});

/**
 * Count the queries run while rendering the users list; call it once first so
 * permission and schema caches are warm and both measurements compare rows only.
 */
function countUsersListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListUsers::class)->call('loadTable');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

it('shows each account status as a badge in the users list', function (): void {
    createSuspendedUser();

    Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->assertSee('Active')
        ->assertSee('Suspended');
});

it('shows the account status badges and filter options in Spanish', function (): void {
    app()->setLocale('es');
    createSuspendedUser();

    Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->assertSee('Activa')
        ->assertSee('Suspendida')
        ->assertDontSee('Suspended');
});

it('still filters by the stored status value in Spanish', function (): void {
    app()->setLocale('es');
    $suspendedUser = createSuspendedUser();
    createUser();

    $testable = Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->set('tableFilters.status', ['value' => 'suspended']);

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->toBe([FilamentTableRecords::recordKey($suspendedUser)]);
});

it('filters the users list by suspended accounts', function (): void {
    $suspendedUser = createSuspendedUser();
    createUser();

    $testable = Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->set('tableFilters.status', ['value' => 'suspended']);

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->toBe([FilamentTableRecords::recordKey($suspendedUser)]);
});

it('filters the users list by active accounts', function (): void {
    $suspendedUser = createSuspendedUser();

    $testable = Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->set('tableFilters.status', ['value' => 'active']);

    expect(FilamentTableRecords::visibleRecordKeys(FilamentTableRecords::table($testable), User::class))
        ->not->toContain(FilamentTableRecords::recordKey($suspendedUser));
});

it('does not add queries per row for the status column', function (): void {
    foreach (range(1, 3) as $index) {
        createSuspendedUser();
    }

    countUsersListQueries();
    $queriesWithFewUsers = countUsersListQueries();

    foreach (range(1, 17) as $index) {
        createSuspendedUser();
    }

    expect(countUsersListQueries())->toBe($queriesWithFewUsers);
});

it('shows the account status in the user detail', function (): void {
    $user = createSuspendedUser();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSee('Account status')
        ->assertSee('Suspended');
});
