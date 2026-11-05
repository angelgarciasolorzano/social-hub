<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Widgets;

use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Override;

/**
 * How many roles of the `admin` guard come with the application, how many were created from the panel,
 * and how many of those nobody holds, in one aggregated query.
 */
class RoleTypesOverview extends StatsOverviewWidget
{
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

    #[Override]
    protected function getHeading(): string
    {
        return TranslatedText::of('admin/roles.widgets.types.heading');
    }

    #[Override]
    protected function getDescription(): string
    {
        return TranslatedText::of('admin/roles.widgets.types.description');
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        ['system' => $systemCount, 'custom' => $customCount, 'unusedCustom' => $unusedCustomCount] = $this->roleTypeCounts();

        return [
            Stat::make(TranslatedText::of('admin/roles.widgets.types.system'), $systemCount)
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
            Stat::make(TranslatedText::of('admin/roles.widgets.types.custom'), $customCount)
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('info'),
            Stat::make(TranslatedText::of('admin/roles.widgets.types.unused_custom'), $unusedCustomCount)
                ->descriptionIcon(Heroicon::OutlinedUserMinus)
                ->color('warning'),
        ];
    }

    /**
     * The three counts in one query: the custom roles without users come from a correlated subquery.
     *
     * @return array{system: int, custom: int, unusedCustom: int}
     */
    private function roleTypeCounts(): array
    {
        $builder = Role::query()
            ->where('guard_name', Role::GUARD_NAME)
            ->where('is_system', false)
            ->doesntHave('users')
            ->selectRaw('COUNT(*)');

        $counts = Role::query()
            ->where('guard_name', Role::GUARD_NAME)
            ->selectRaw('COALESCE(SUM(CASE WHEN is_system = 1 THEN 1 ELSE 0 END), 0) AS system_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_system = 0 THEN 1 ELSE 0 END), 0) AS custom_count')
            ->selectSub($builder, 'unused_custom_count')
            ->toBase()
            ->first();

        return [
            'system' => $this->countIn($counts, 'system_count'),
            'custom' => $this->countIn($counts, 'custom_count'),
            'unusedCustom' => $this->countIn($counts, 'unused_custom_count'),
        ];
    }

    private function countIn(?object $counts, string $column): int
    {
        $count = $counts === null ? 0 : (get_object_vars($counts)[$column] ?? 0);

        return is_numeric($count) ? (int) $count : 0;
    }
}
