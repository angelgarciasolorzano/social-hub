<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RoleTypesOverview;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\WidgetStats;
use App\Support\Translation\TranslatedText;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{system: Stat, custom: Stat, unusedCustom: Stat}
 */
function roleTypesOverviewStats(): array
{
    [$system, $custom, $unusedCustom] = WidgetStats::of(RoleTypesOverview::class, 3);

    return ['system' => $system, 'custom' => $custom, 'unusedCustom' => $unusedCustom];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('counts system roles, custom roles and custom roles nobody holds in one query', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    $heldCustomRole = RoleFactory::new()->createOne();

    RoleFactory::new()->createOne();

    createUser()->assignRole($heldCustomRole);

    Role::query()->create(['name' => 'custom-web-role', 'guard_name' => 'web']);

    $component = Livewire::test(RoleTypesOverview::class)->instance();

    DB::flushQueryLog();
    DB::enableQueryLog();

    new ReflectionMethod(RoleTypesOverview::class, 'getStats')->invoke($component);

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    $stats = roleTypesOverviewStats();

    expect($stats['system']->getValue())->toBe(count(AdminRole::cases()))
        ->and($stats['custom']->getValue())->toBe(2)
        ->and($stats['unusedCustom']->getValue())->toBe(1)
        ->and($stats['unusedCustom']->getLabel())->toBe(TranslatedText::of('admin/roles.widgets.types.unused_custom'))
        ->and($queryCount)->toBe(1);
});

it('is hidden from an administrator who cannot list roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminUsersReader);

    expect(RoleTypesOverview::canView())->toBeFalse();
});

it('is shown to a roles reader', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    expect(RoleTypesOverview::canView())->toBeTrue();
});
