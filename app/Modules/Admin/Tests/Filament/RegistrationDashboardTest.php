<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Widgets\OperationalSummaryWidget;
use App\Modules\Admin\Filament\Widgets\RegistrationsChart;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\RegistrationDashboardTestData;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('discovers the registration widgets through the existing admin panel discovery', function (): void {
    expect(Filament::getPanel('admin')->getWidgets())
        ->toContain(RegistrationsChart::class, RegistrationSummaryWidget::class, OperationalSummaryWidget::class);
});

it('renders both registration widgets for dashboard readers', function (): void {
    $authenticatedUser = RegistrationDashboardTestData::createDashboardReader();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertSeeText('Total accounts');

    Livewire::test(RegistrationsChart::class)
        ->assertSeeText('New user registrations');
});

it('forbids panel users without dashboard permission from both registration widgets', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);

    expect($authenticatedUser->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class)
        ->assertForbidden();

    Livewire::test(RegistrationsChart::class)
        ->assertForbidden();
});
