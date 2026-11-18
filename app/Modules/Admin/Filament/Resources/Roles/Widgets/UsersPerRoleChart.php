<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Widgets;

use App\Modules\Admin\Filament\Resources\Roles\RoleResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use Filament\Widgets\ChartWidget;
use Override;

/**
 * How many users hold each role of the `admin` guard: system roles in catalog order, then custom roles
 * by name.
 */
class UsersPerRoleChart extends ChartWidget
{
    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected string $color = 'primary';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public function getHeading(): string
    {
        return TranslatedText::of('admin/roles.widgets.users_per_role.heading');
    }

    #[Override]
    public function getDescription(): string
    {
        return TranslatedText::of('admin/roles.widgets.users_per_role.description');
    }

    #[Override]
    public function getEmptyStateHeading(): string
    {
        return TranslatedText::of('admin/roles.widgets.users_per_role.empty_heading');
    }

    #[Override]
    public function getEmptyStateDescription(): string
    {
        return TranslatedText::of('admin/roles.widgets.users_per_role.empty_description');
    }

    #[Override]
    public static function canView(): bool
    {
        return RoleResource::canViewAny();
    }

    /**
     * @return array{}|array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    #[Override]
    protected function getData(): array
    {
        $labels = [];
        $counts = [];

        foreach (Role::inDisplayOrder(Role::query()->admin()->withCount('users')->get()) as $role) {
            $usersCount = $role->getAttribute('users_count');
            $labels[] = $role->displayName();
            $counts[] = \is_int($usersCount) ? $usersCount : 0;
        }

        if ($labels === []) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => TranslatedText::of('admin/roles.widgets.users_per_role.dataset'),
                'data' => $counts,
            ]],
            'labels' => $labels,
        ];
    }

    #[Override]
    protected function getType(): string
    {
        return 'bar';
    }
}
