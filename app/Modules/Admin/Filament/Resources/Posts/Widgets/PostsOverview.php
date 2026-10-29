<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Widgets;

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Override;

class PostsOverview extends StatsOverviewWidget
{
    use BuildsDailyActivityCharts;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    protected function getHeading(): string
    {
        return TranslatedText::of('admin/posts.widgets.overview.heading');
    }

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('viewAny', Post::class);
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
            Stat::make(TranslatedText::of('admin/posts.widgets.overview.posts'), $builder->count())
                ->description(TranslatedText::of('admin/posts.widgets.overview.posts_description'))
                ->chart($this->getDailyActivityChartData($builder, $startDate, $endDate))
                ->color('primary'),
            Stat::make(TranslatedText::of('admin/posts.widgets.overview.comments'), $commentQuery->count())
                ->description(TranslatedText::of('admin/posts.widgets.overview.comments_description'))
                ->chart($this->getDailyActivityChartData($commentQuery, $startDate, $endDate))
                ->color('info'),
            Stat::make(TranslatedText::of('admin/posts.widgets.overview.likes'), $likeQuery->count('*'))
                ->description(TranslatedText::of('admin/posts.widgets.overview.likes_description'))
                ->chart($this->getDailyActivityChartData($likeQuery, $startDate, $endDate))
                ->color('warning'),
        ];
    }
}
