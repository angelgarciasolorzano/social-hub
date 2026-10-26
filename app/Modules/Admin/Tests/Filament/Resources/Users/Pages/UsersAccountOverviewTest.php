<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Widgets\RegistrationSummaryWidget;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Database\Factories\UserFactory;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows pending email verification accounts in the users overview', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    createUser();
    UserFactory::new()->unverified()->count(2)->create();

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class, ['showPendingVerification' => true])
        ->assertSeeTextInOrder([
            'Total accounts',
            '4',
            'Verified accounts',
            '2',
            'Pending verification',
            '2',
        ]);
});

it('shows zero pending email verification accounts when all accounts are verified', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    Livewire::test(RegistrationSummaryWidget::class, ['showPendingVerification' => true])
        ->assertSeeTextInOrder([
            'Verified accounts',
            '1',
            'Pending verification',
            '0',
        ]);
});

it('enables the pending verification stat only on the users list', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole([
        AdminRole::Admin->value,
        AdminRole::AdminUsersReader->value,
    ]);

    $this->actingAs($authenticatedUser, 'admin');

    $testable = Livewire::test(ListUsers::class);
    $component = $testable->instance();

    throw_unless(
        $component instanceof ListUsers,
        LogicException::class,
        'The users list page must be mounted.',
    );

    $headerWidgetsMethod = new ReflectionMethod(ListUsers::class, 'getHeaderWidgets');
    $headerWidgets = $headerWidgetsMethod->invoke($component);

    throw_unless(
        is_array($headerWidgets),
        LogicException::class,
        'The users page must define its header widgets.',
    );

    $accountOverview = $headerWidgets[0] ?? null;

    throw_unless(
        $accountOverview instanceof WidgetConfiguration,
        LogicException::class,
        'The users page must configure its account overview widget.',
    );

    expect($accountOverview->widget)->toBe(RegistrationSummaryWidget::class)
        ->and($accountOverview->getProperties())
        ->toBe(['showPendingVerification' => true]);
});
