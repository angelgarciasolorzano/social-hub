<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Widgets;

use App\Like\Models\Like;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class PostsOverview extends StatsOverviewWidget
{
    #[Override]
    protected ?string $heading = 'Posts overview';

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminPostsViewAny->value);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $startDate = CarbonImmutable::today()->subDays(6);
        $endDate = $startDate->addDays(7);

        $builder = Post::query();
        $commentQuery = Comment::query()
            ->where('commentable_type', Post::MORPH_NAME);
        $likeQuery = Like::query()
            ->where('likeable_type', Post::MORPH_NAME);

        return [
            Stat::make('Posts', $builder->count())
                ->description('New posts per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($builder, $startDate, $endDate))
                ->color('primary'),
            Stat::make('Comments', $commentQuery->count())
                ->description('New comments on posts per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($commentQuery, $startDate, $endDate))
                ->color('info'),
            Stat::make('Likes', $likeQuery->count('*'))
                ->description('New likes on posts per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($likeQuery, $startDate, $endDate))
                ->color('warning'),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<float>
     */
    private function getDailyActivityChartData(
        Builder $query,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): array {
        $activityCountsByDate = (clone $query)
            ->toBase()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'activity_date');

        return collect(range(0, 6))
            ->map(function (int $dayOffset) use ($activityCountsByDate, $startDate): float {
                $activityCount = $activityCountsByDate->get(
                    $startDate->addDays($dayOffset)->toDateString(),
                    0,
                );

                return is_numeric($activityCount) ? (float) $activityCount : 0.0;
            })
            ->all();
    }
}
