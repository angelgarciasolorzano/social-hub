<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\RegistrationDashboardTestData;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows accurate total and verified account summaries', function (): void {
    $authenticatedUser = RegistrationDashboardTestData::createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    RegistrationDashboardTestData::createDashboardRegistration('2026-06-10 10:00:00');
    RegistrationDashboardTestData::createDashboardRegistration('2026-06-11 10:00:00', verified: false);

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertSeeText('Total accounts')
        ->assertSeeText('All registered user accounts')
        ->assertSeeText('Verified accounts')
        ->assertSeeText('Accounts with a verified email address')
        ->assertSeeText('3')
        ->assertSeeText('2');
});

it('shows the account summary in Spanish, including pending verification', function (): void {
    app()->setLocale('es');

    $authenticatedUser = RegistrationDashboardTestData::createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    RegistrationDashboardTestData::createDashboardRegistration('2026-06-11 10:00:00', verified: false);

    Livewire::test(RegistrationSummaryWidget::class, ['showPendingVerification' => true])
        ->assertSeeText('Resumen de cuentas')
        ->assertSeeText('Cuentas totales')
        ->assertSeeText('Todas las cuentas de usuario registradas')
        ->assertSeeText('Cuentas verificadas')
        ->assertSeeText('Cuentas con el correo electrónico verificado')
        ->assertSeeText('Verificación pendiente')
        ->assertSeeText('Cuentas que aún no verifican su correo electrónico')
        ->assertDontSeeText('Account overview');
});

it('shows seven day registration trends on the account summary cards', function (): void {
    $authenticatedUser = RegistrationDashboardTestData::createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    RegistrationDashboardTestData::createDashboardRegistration('2026-06-09 08:00:00');
    RegistrationDashboardTestData::createDashboardRegistration('2026-06-10 08:00:00', verified: false);
    RegistrationDashboardTestData::createDashboardRegistration('2026-06-10 09:00:00');
    RegistrationDashboardTestData::createDashboardRegistration('2026-06-15 08:00:00', verified: false);

    $component = Livewire::test(RegistrationSummaryWidget::class)->instance();

    throw_unless($component instanceof RegistrationSummaryWidget, LogicException::class, 'The summary widget must be mounted.');

    /** @var RegistrationSummaryWidget $component */
    $statsMethod = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats');

    $stats = $statsMethod->invoke($component);

    throw_unless(
        is_array($stats)
            && count($stats) === 2
            && $stats[0] instanceof Stat
            && $stats[1] instanceof Stat,
        LogicException::class,
        'The account summary must return two stat cards.',
    );

    /** @var array{0: Stat, 1: Stat} $stats */
    expect($stats[0]->getChart())
        ->toBe([1.0, 2.0, 0.0, 0.0, 0.0, 0.0, 1.0])
        ->and($stats[1]->getChart())
        ->toBe([1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});
