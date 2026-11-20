<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Widgets\FriendshipsOverviewWidget;
use App\Modules\Admin\Tests\Support\AdminFilamentTestContext;
use App\Modules\Admin\Tests\Support\QueryLog;
use App\Modules\Friendship\Models\Friendship;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The stat cards the widget renders for the signed-in administrator.
 *
 * @return array{0: Stat, 1: Stat, 2: Stat}
 */
function friendshipsOverviewStats(): array
{
    $component = Livewire::test(FriendshipsOverviewWidget::class)->instance();

    throw_unless($component instanceof FriendshipsOverviewWidget, LogicException::class, 'The friendships widget must be mounted.');

    $stats = new ReflectionMethod(StatsOverviewWidget::class, 'getCachedStats')->invoke($component);

    throw_unless(
        is_array($stats) && count($stats) === 3 && $stats[0] instanceof Stat && $stats[1] instanceof Stat && $stats[2] instanceof Stat,
        LogicException::class,
        'The friendships widget must return three stat cards.',
    );

    /** @var array{0: Stat, 1: Stat, 2: Stat} $stats */
    return $stats;
}

function backdateFriendship(Friendship $friendship, string $createdAt, ?string $acceptedAt = null): void
{
    $friendship->forceFill(['created_at' => $createdAt, 'accepted_at' => $acceptedAt])->save();
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-30 12:00:00'));

    /** @var TestCase $testCase */
    $testCase = $this;

    AdminFilamentTestContext::configure($testCase);
});

it('shows the accepted and pending totals with a thirty day chart each', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    backdateFriendship(createFriendship(), '2026-01-01 10:00:00', '2026-01-02 10:00:00');
    backdateFriendship(createFriendship(), '2026-05-20 10:00:00', '2026-06-01 10:00:00');
    backdateFriendship(createFriendship(), '2026-06-29 10:00:00', '2026-06-30 09:00:00');
    backdateFriendship(createFriendRequest(), '2026-06-30 08:00:00');
    backdateFriendship(createFriendRequest(), '2026-05-01 08:00:00');

    [$accepted, $pending, $new] = friendshipsOverviewStats();
    $acceptedChart = $accepted->getChart() ?? [];
    $pendingChart = $pending->getChart() ?? [];

    expect($accepted->getValue())->toBe(3)
        ->and($acceptedChart)->toHaveCount(30)
        ->and($acceptedChart[0] ?? null)->toBe(1.0)
        ->and($acceptedChart[29] ?? null)->toBe(1.0)
        ->and(array_sum($acceptedChart))->toBe(2.0)
        ->and($pending->getValue())->toBe(2)
        ->and($pendingChart)->toHaveCount(30)
        ->and(array_sum($pendingChart))->toBe(1.0)
        ->and($new->getValue())->toBe(2);
});

it('shows zeros when there are no friendships', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    [$accepted, $pending, $new] = friendshipsOverviewStats();

    expect($accepted->getValue())->toBe(0)
        ->and($pending->getValue())->toBe(0)
        ->and($new->getValue())->toBe(0)
        ->and($accepted->getChart())->toBe(array_fill(0, 30, 0.0));
});

it('shows the widget in Spanish', function (): void {
    app()->setLocale('es');

    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    Livewire::test(FriendshipsOverviewWidget::class)
        ->assertSeeText('Amistades')
        ->assertSeeText('Solicitudes pendientes')
        ->assertSeeText('Amistades nuevas (30 días)')
        ->assertDontSeeText('Pending requests');
});

it('hides the widget from the dashboard without admin.users.friendships', function (): void {
    $authenticatedUser = createUser();
    $authenticatedUser->assignRole(AdminRole::Admin->value);
    grantAdminPermissionThroughTestRole($authenticatedUser, AdminPermission::AdminDashboardView);

    $this->actingAs($authenticatedUser, 'admin');

    expect(FriendshipsOverviewWidget::canView())->toBeFalse();

    Livewire::test(FriendshipsOverviewWidget::class)->assertForbidden();
});

it('is discovered by the panel and shown with admin.users.friendships', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    expect(Filament::getPanel('admin')->getWidgets())->toContain(FriendshipsOverviewWidget::class)
        ->and(FriendshipsOverviewWidget::canView())->toBeTrue();
});

it('reads 3 or 30 friendships with the same number of queries', function (): void {
    AdminFilamentTestContext::actingAsAdminWithRoles($this, AdminRole::Admin, AdminRole::AdminUsersReader);

    $render = static fn (): array => friendshipsOverviewStats();

    foreach (range(1, 3) as $sequence) {
        $sequence % 2 === 0 ? createFriendship() : createFriendRequest();
    }

    QueryLog::count($render);
    $fewQueries = QueryLog::count($render);

    foreach (range(1, 27) as $sequence) {
        $sequence % 2 === 0 ? createFriendship() : createFriendRequest();
    }

    expect(QueryLog::count($render))->toBe($fewQueries);
});
