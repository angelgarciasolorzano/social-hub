<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Widgets;

use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Override;

/**
 * Who can enter the panel, which cataloged roles nobody holds and how often authorization changed.
 */
class RolesOverview extends StatsOverviewWidget
{
    use BuildsDailyActivityCharts;

    private const int CHANGES_WINDOW_DAYS = 30;

    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $today = CarbonImmutable::today();
        $authorizationChanges = $this->authorizationChanges();

        return [
            Stat::make(
                TranslatedText::of('admin/roles.widgets.overview.administrators.label'),
                User::query()->permission(AdminPermission::AdminAccess->value)->count(),
            )
                ->description(TranslatedText::of('admin/roles.widgets.overview.administrators.description'))
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('primary'),
            Stat::make(
                TranslatedText::of('admin/roles.widgets.overview.unused_roles.label'),
                Role::query()
                    ->where('guard_name', 'admin')
                    ->whereIn('name', array_map(static fn (AdminRole $adminRole): string => $adminRole->value, AdminRole::cases()))
                    ->doesntHave('users')
                    ->count(),
            )
                ->description(TranslatedText::of('admin/roles.widgets.overview.unused_roles.description'))
                ->descriptionIcon(Heroicon::OutlinedUserMinus)
                ->color('gray'),
            Stat::make(
                TranslatedText::of('admin/roles.widgets.overview.changes.label'),
                (clone $authorizationChanges)
                    ->where('created_at', '>=', $today->subDays(self::CHANGES_WINDOW_DAYS - 1))
                    ->count(),
            )
                ->description(TranslatedText::of('admin/roles.widgets.overview.changes.description'))
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->chart($this->getDailyActivityChartData($authorizationChanges, $today->subDays(6), $today->addDay()))
                ->color('warning'),
        ];
    }

    /**
     * The roles granted to or revoked from users and the permissions granted to or revoked from roles.
     *
     * @return Builder<ActivityLog>
     */
    private function authorizationChanges(): Builder
    {
        $builder = ActivityLog::query();

        $builder->whereIn('event', [
            ActivityLogAdminEvent::RoleGranted->value,
            ActivityLogAdminEvent::RoleRevoked->value,
            ActivityLogAdminEvent::RolePermissionGranted->value,
            ActivityLogAdminEvent::RolePermissionRevoked->value,
        ]);

        return $builder;
    }
}
