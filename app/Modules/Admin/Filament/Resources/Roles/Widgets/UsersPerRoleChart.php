<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Widgets;

use App\Modules\Admin\Enums\AdminRole;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\Permission\Models\Role;

/**
 * How many users hold each cataloged role, in catalog order.
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
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Role::class);
    }

    /**
     * @return array{}|array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    #[Override]
    protected function getData(): array
    {
        $usersCountByRole = [];

        foreach (Role::query()->where('guard_name', 'admin')->withCount('users')->get() as $role) {
            $usersCount = $role->getAttribute('users_count');
            $usersCountByRole[$role->name] = \is_int($usersCount) ? $usersCount : 0;
        }

        $labels = [];
        $counts = [];

        foreach (AdminRole::cases() as $adminRole) {
            if (\array_key_exists($adminRole->value, $usersCountByRole)) {
                $labels[] = $adminRole->label();
                $counts[] = $usersCountByRole[$adminRole->value];
            }
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
