<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Resources\Roles\Widgets\UsersPerRoleChart;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\User\Enums\PreferredLocale;
use Filament\Widgets\ChartWidget;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The chart's user counts, keyed by bar label.
 *
 * @return array<string, int>
 */
function usersPerRoleChartCounts(): array
{
    $component = Livewire::test(UsersPerRoleChart::class)->instance();

    throw_unless($component instanceof UsersPerRoleChart, LogicException::class, 'The users per role chart must be mounted.');

    $chartData = new ReflectionMethod(ChartWidget::class, 'getCachedData')->invoke($component);

    $labels = is_array($chartData) ? ($chartData['labels'] ?? null) : null;
    $datasets = is_array($chartData) ? ($chartData['datasets'] ?? null) : null;
    $firstDataset = is_array($datasets) ? ($datasets[0] ?? null) : null;
    $counts = is_array($firstDataset) ? ($firstDataset['data'] ?? null) : null;

    throw_unless(
        is_array($labels) && is_array($counts) && count($labels) === count($counts),
        LogicException::class,
        'The users per role chart must return one count per label.',
    );

    $countsByLabel = [];

    foreach (array_values($labels) as $index => $label) {
        $count = array_values($counts)[$index];

        throw_unless(is_string($label) && is_int($count), LogicException::class, 'The chart labels must be strings and its counts integers.');

        $countsByLabel[$label] = $count;
    }

    return $countsByLabel;
}

beforeEach(function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('has one bar per cataloged role with its users, labeled in Spanish', function (): void {
    app()->setLocale(PreferredLocale::SPANISH->value);

    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminRolesReader);
    createUser()->assignRole(AdminRole::AdminPostsReader->value);
    createUser()->assignRole(AdminRole::AdminPostsReader->value);
    Role::query()->create(['name' => 'legacy-role', 'guard_name' => 'admin']);

    $counts = usersPerRoleChartCounts();
    $label = static fn (AdminRole $adminRole): string => translatedTextIn(PreferredLocale::SPANISH, 'admin/roles.catalog.roles.'.$adminRole->translationKey().'.label');

    expect(array_keys($counts))->toBe(array_map($label, AdminRole::cases()))
        ->and($counts[$label(AdminRole::AdminPostsReader)])->toBe(2)
        ->and($counts[$label(AdminRole::Admin)])->toBe(1)
        ->and($counts[$label(AdminRole::AdminRolesReader)])->toBe(1)
        ->and($counts[$label(AdminRole::AdminBackupsOperator)])->toBe(0);
});

it('is hidden from an administrator who cannot list roles', function (): void {
    /** @var TestCase $testCase */
    $testCase = $this;
    AdminFilamentTestContext::actingAsAdminWithRoles($testCase, AdminRole::Admin, AdminRole::AdminPostsReader);

    expect(UsersPerRoleChart::canView())->toBeFalse();
});
