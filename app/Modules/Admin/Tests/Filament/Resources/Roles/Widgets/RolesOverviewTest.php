<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\RolesOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{administrators: Stat, unusedRoles: Stat, changes: Stat}
 */
function rolesOverviewStats(): array
{
    $component = Livewire::test(RolesOverview::class)->instance();

    throw_unless($component instanceof RolesOverview, LogicException::class, 'The roles overview widget must be mounted.');

    $stats = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats')->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 3
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat
            && $stats[2] instanceof Stat,
        LogicException::class,
        'The roles overview must return three stat cards.',
    );

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return [
        'administrators' => $stats[0],
        'unusedRoles' => $stats[1],
        'changes' => $stats[2],
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('counts administrators, unused cataloged roles and authorization changes of the last 30 days', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-30 12:00:00'));

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    createAdminCommandActor();
    createAdminCommandActor();

    foreach (AdminRole::cases() as $adminRole) {
        if ($adminRole !== AdminRole::AdminBackupsOperator) {
            createUser()->assignRole($adminRole->value);
        }
    }

    foreach (['2026-06-01 00:00:00', '2026-06-15 10:00:00', '2026-06-29 09:00:00', '2026-06-30 08:00:00'] as $createdAt) {
        ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RolePermissionGranted)->createOne(['created_at' => $createdAt]);
    }

    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->createOne(['created_at' => '2026-05-31 23:59:59']);
    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::AccountSuspended)->createOne(['created_at' => '2026-06-30 08:00:00']);

    $stats = rolesOverviewStats();

    expect($stats['administrators']->getValue())->toBe(4)
        ->and($stats['unusedRoles']->getValue())->toBe(1)
        ->and($stats['changes']->getValue())->toBe(4)
        ->and($stats['changes']->getChart())->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 1.0]);
});

it('shows its labels in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);

    Livewire::test(RolesOverview::class)
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.widgets.overview.administrators.label'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.widgets.overview.unused_roles.label'))
        ->assertSee(translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.widgets.overview.changes.label'));
});

it('is hidden from an administrator who cannot list roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminPostsReader);

    expect(RolesOverview::canView())->toBeFalse();
});
