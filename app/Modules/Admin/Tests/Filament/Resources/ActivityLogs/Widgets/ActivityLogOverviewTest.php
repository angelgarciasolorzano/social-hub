<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogTrustedDeviceEvent;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets\ActivityLogOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @return array{failedSignIns: Stat, trustedDeviceChanges: Stat, adminActions: Stat}
 */
function activityLogOverviewStatsForTesting(): array
{
    $component = Livewire::test(ActivityLogOverview::class)->instance();

    throw_unless($component instanceof ActivityLogOverview, LogicException::class, 'The activity overview widget must be mounted.');

    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');
    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 3
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat
            && $stats[2] instanceof Stat,
        LogicException::class,
        'The activity overview must return three stat cards.',
    );

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return [
        'failedSignIns' => $stats[0],
        'trustedDeviceChanges' => $stats[1],
        'adminActions' => $stats[2],
    ];
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('counts security and admin activity within the fixed seven-day window', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);

    ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::LoginFailed)->createOne([
        'created_at' => '2026-06-09 00:00:00',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::LoginFailed)->createOne([
        'created_at' => '2026-06-15 23:59:59',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogAuthEvent::LoginFailed)->createOne([
        'created_at' => '2026-06-08 23:59:59',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogTrustedDeviceEvent::Created)->createOne([
        'created_at' => '2026-06-10 10:00:00',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogTrustedDeviceEvent::Revoked)->createOne([
        'created_at' => '2026-06-15 10:00:00',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogTrustedDeviceEvent::Renewed)->createOne([
        'created_at' => '2026-06-08 10:00:00',
    ]);
    ActivityLogFactory::new()->forEvent(ActivityLogAdminEvent::RoleGranted)->createOne([
        'created_at' => '2026-06-11 10:00:00',
    ]);

    $testable = Livewire::test(ActivityLogOverview::class)
        ->assertOk()
        ->assertSeeText('Security and administration (last 7 days)')
        ->assertSeeText('Failed sign-ins')
        ->assertSeeText('Trusted device changes')
        ->assertSeeText('Admin actions');

    expect($testable->instance())->toBeInstanceOf(ActivityLogOverview::class);

    $stats = activityLogOverviewStatsForTesting();

    expect($stats['failedSignIns']->getValue())
        ->toBe(2)
        ->and($stats['trustedDeviceChanges']->getValue())
        ->toBe(2)
        ->and($stats['adminActions']->getValue())
        ->toBe(1);
});

it('shows zero counts when there is no recent security or admin activity', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);

    $stats = activityLogOverviewStatsForTesting();

    expect($stats['failedSignIns']->getValue())
        ->toBe(0)
        ->and($stats['trustedDeviceChanges']->getValue())
        ->toBe(0)
        ->and($stats['adminActions']->getValue())
        ->toBe(0);
});

it('denies the widget to an administrator without activity access', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    Livewire::test(ActivityLogOverview::class)->assertForbidden();
});
