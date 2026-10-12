<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Widgets;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class PostActivityOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && $authenticatedUser->can(AdminPermission::AdminPostsView->value);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $selectedRecord = $this->record;

        if (! $selectedRecord instanceof Post) {
            return [];
        }

        $post = PostResource::getEloquentQuery()->find($selectedRecord->getKey());

        if (! $post instanceof Post || ! PostResource::canView($post)) {
            return [];
        }

        $startDate = CarbonImmutable::today()->subDays(6);
        $endDate = $startDate->addDays(7);

        /** @var Builder<Comment> $builder */
        $builder = Comment::query()
            ->whereIn('commentable_id', $post->comments()->getQuery()->select('comments.id'))
            ->where('commentable_type', Comment::MORPH_NAME);

        return [
            Stat::make('Comments', $post->comments()->count())
                ->description('New comments per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($post->comments()->getQuery(), $startDate, $endDate))
                ->color('primary'),
            Stat::make('Replies', $builder->count())
                ->description('New replies per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($builder, $startDate, $endDate))
                ->color('info'),
            Stat::make('Likes', $post->likes()->count())
                ->description('New likes per day over the last 7 days')
                ->chart($this->getDailyActivityChartData($post->likes()->getQuery(), $startDate, $endDate))
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
