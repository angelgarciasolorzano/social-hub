<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Widgets;

use App\Modules\Admin\Filament\Concerns\BuildsDailyActivityCharts;
use App\Modules\Admin\Filament\Resources\Posts\PostResource;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class PostActivityOverview extends StatsOverviewWidget
{
    use BuildsDailyActivityCharts;

    public ?Model $record = null;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', Post::class);
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
            Stat::make(TranslatedText::of('admin/posts.widgets.activity.comments'), $post->comments()->count())
                ->description(TranslatedText::of('admin/posts.widgets.activity.comments_description'))
                ->chart($this->getDailyActivityChartData($post->comments()->getQuery(), $startDate, $endDate))
                ->color('primary'),
            Stat::make(TranslatedText::of('admin/posts.widgets.activity.replies'), $builder->count())
                ->description(TranslatedText::of('admin/posts.widgets.activity.replies_description'))
                ->chart($this->getDailyActivityChartData($builder, $startDate, $endDate))
                ->color('info'),
            Stat::make(TranslatedText::of('admin/posts.widgets.activity.likes'), $post->likes()->count())
                ->description(TranslatedText::of('admin/posts.widgets.activity.likes_description'))
                ->chart($this->getDailyActivityChartData($post->likes()->getQuery(), $startDate, $endDate))
                ->color('warning'),
        ];
    }
}
