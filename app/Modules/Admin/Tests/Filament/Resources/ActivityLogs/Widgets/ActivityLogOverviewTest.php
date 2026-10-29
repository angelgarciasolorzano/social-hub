<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Database\Factories\ActivityLogFactory;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogTrustedDeviceEvent;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Widgets\ActivityLogOverview;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
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

it('shows localized security and admin metrics within the fixed seven-day window', function (
    PreferredLocale $preferredLocale,
    string $expectedHeading,
    string $expectedFailedSignIns,
    string $expectedFailedSignInsDescription,
    string $expectedTrustedDeviceChanges,
    string $expectedTrustedDeviceChangesDescription,
    string $expectedAdminActions,
    string $expectedAdminActionsDescription,
): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    app()->setLocale($preferredLocale->value);

    $user = AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);
    $user->update(['preferred_locale' => $preferredLocale->value]);

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
        ->assertSeeText($expectedHeading)
        ->assertSeeText($expectedFailedSignIns)
        ->assertSeeText($expectedFailedSignInsDescription)
        ->assertSeeText($expectedTrustedDeviceChanges)
        ->assertSeeText($expectedTrustedDeviceChangesDescription)
        ->assertSeeText($expectedAdminActions)
        ->assertSeeText($expectedAdminActionsDescription);

    expect($testable->instance())->toBeInstanceOf(ActivityLogOverview::class);

    $stats = activityLogOverviewStatsForTesting();

    expect($stats['failedSignIns']->getValue())
        ->toBe(2)
        ->and($stats['trustedDeviceChanges']->getValue())
        ->toBe(2)
        ->and($stats['adminActions']->getValue())
        ->toBe(1)
        ->and($stats['failedSignIns']->getColor())
        ->toBe('danger')
        ->and($stats['trustedDeviceChanges']->getColor())
        ->toBe('info')
        ->and($stats['adminActions']->getColor())
        ->toBe('warning');
})->with([
    'English' => [
        PreferredLocale::ENGLISH,
        'Security and administration (last 7 days)',
        'Failed sign-ins',
        'Authentication attempts that were rejected',
        'Trusted device changes',
        'Devices added, renamed, renewed, revoked or reactivated',
        'Admin actions',
        'Roles, permissions, invitations, accounts and backups',
    ],
    'Spanish' => [
        PreferredLocale::SPANISH,
        'Seguridad y administración (últimos 7 días)',
        'Inicios de sesión fallidos',
        'Intentos de autenticación rechazados',
        'Cambios en dispositivos de confianza',
        'Dispositivos agregados, renombrados, renovados, revocados o reactivados',
        'Acciones administrativas',
        'Roles, permisos, invitaciones, cuentas y respaldos',
    ],
]);

it('shows zero counts when there is no recent security or admin activity', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminActivityReader);

    Livewire::test(ActivityLogOverview::class)
        ->assertOk()
        ->assertSeeText('0');

    $stats = activityLogOverviewStatsForTesting();

    expect($stats['failedSignIns']->getValue())
        ->toBe(0)
        ->and($stats['trustedDeviceChanges']->getValue())
        ->toBe(0)
        ->and($stats['adminActions']->getValue())
        ->toBe(0)
        ->and($stats['failedSignIns']->getColor())
        ->toBe('success');
});

it('denies the widget to an administrator without activity access', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    Livewire::test(ActivityLogOverview::class)->assertForbidden();
});
