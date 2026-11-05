<?php

declare(strict_types=1);

use App\Modules\Admin\Database\Factories\RoleFactory;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RoleTypesOverview;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Support\Translation\TranslatedText;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{system: Stat, custom: Stat, unusedCustom: Stat}
 */
function roleTypesOverviewStats(): array
{
    $component = Livewire::test(RoleTypesOverview::class)->instance();

    throw_unless($component instanceof RoleTypesOverview, LogicException::class, 'The role types widget must be mounted.');

    $stats = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats')->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 3
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat
            && $stats[2] instanceof Stat,
        LogicException::class,
        'The role types widget must return three stat cards.',
    );

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return [
        'system' => $stats[0],
        'custom' => $stats[1],
        'unusedCustom' => $stats[2],
    ];
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
