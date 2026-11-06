<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\RelationManagers\RoleUsersRelationManager;
use App\Modules\Admin\Filament\Resources\Roles\Tables\RoleUsersTable;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\AccountStatus;
use App\User\Enums\PreferredLocale;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A role whose holders are: one with MFA who signed in yesterday, one without MFA who never signed in,
 * and one suspended without MFA who last signed in 120 days ago.
 */
function roleWithMixedHolders(): Role
{
    $role = RoleFactory::new()->createOne();

    $user = createUser();
    $user->forceFill(['app_authentication_secret' => 'PANELSECRET', 'last_login_at' => now()->subDay()])->save();

    $suspendedHolder = createUser();
    $suspendedHolder->forceFill(['status' => AccountStatus::Suspended, 'last_login_at' => now()->subDays(120)])->save();

    foreach ([$user, createUser(), $suspendedHolder] as $holder) {
        $holder->assignRole($role);
    }

    return $role;
}

/**
 * The summary sentence in the given language with the given counts.
 */
function expectedHoldersSummary(PreferredLocale $preferredLocale, int $withoutMfa, int $inactive, int $suspended): string
{
    return str_replace(
        [':without_mfa', ':inactive', ':suspended'],
        [(string) $withoutMfa, (string) $inactive, (string) $suspended],
        translatedTextIn($preferredLocale, 'admin/roles.relations.users.summary'),
    );
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('counts the holders without panel MFA, without a recent sign-in and suspended', function (PreferredLocale $preferredLocale): void {
    app()->setLocale($preferredLocale->value);
    $role = roleWithMixedHolders();

    expect(RoleUsersTable::holdersSummary($role))->toBe(expectedHoldersSummary($preferredLocale, 2, 2, 1));
})->with([PreferredLocale::ENGLISH, PreferredLocale::SPANISH]);

it('shows the summary above the users of the role', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader, AdminRole::AdminUsersReader);
    $role = roleWithMixedHolders();

    Livewire::test(RoleUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class])
        ->assertSeeText(expectedHoldersSummary(PreferredLocale::ENGLISH, 2, 2, 1));
});

it('shows no summary for a role nobody holds', function (): void {
    createUser();

    expect(RoleUsersTable::holdersSummary(RoleFactory::new()->createOne()))->toBeNull();
});

it('computes the summary in one query', function (): void {
    $role = roleWithMixedHolders();

    DB::flushQueryLog();
    DB::enableQueryLog();

    RoleUsersTable::holdersSummary($role);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBe(1);
});
