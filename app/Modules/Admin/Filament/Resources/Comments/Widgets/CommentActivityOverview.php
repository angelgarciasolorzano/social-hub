<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\Widgets;

use App\Modules\Admin\Filament\Resources\Comments\CommentResource;
use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class CommentActivityOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    #[Override]
    protected ?string $pollingInterval = null;

    #[Override]
    public static function canView(): bool
    {
        $authenticatedUser = auth('admin')->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', Comment::class);
    }

    /**
     * @return array<Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $selectedRecord = $this->record;

        if (! $selectedRecord instanceof Comment) {
            return [];
        }

        $comment = CommentResource::getEloquentQuery()
            ->withCount(['comments', 'likes'])
            ->find($selectedRecord->getKey());

        if (! $comment instanceof Comment || ! CommentResource::canView($comment)) {
            return [];
        }

        return [
            Stat::make('Replies', $comment->repliesCount())
                ->description('Replies to this comment')
                ->color('info'),
            Stat::make('Likes', $comment->likesCount())
                ->description('Likes for this comment')
                ->color('warning'),
        ];
    }
}
