<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\RelationManagers;

use App\Modules\Admin\Filament\Resources\Posts\Pages\ViewPost;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class CommentsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'comments';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Post || $pageClass !== ViewPost::class) {
            return false;
        }

        $owner = $ownerRecord->user;
        $authenticatedUser = Filament::auth()->user();

        return $owner instanceof User
            && $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $owner);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user:id,name'))
            ->columns([
                TextColumn::make('content')
                    ->label('Content')
                    ->limit(100),
                TextColumn::make('user.name')
                    ->label('Author'),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Comment details')
                    ->mutateRecordDataUsing(function (array $data, Comment $comment): array {
                        $comment->loadMissing('comments.user:id,name');

                        $data['user'] = ['name' => $comment->user->name];

                        $data['replies'] = $comment->comments
                            ->map(static fn (Comment $comment): array => [
                                'user' => ['name' => $comment->user->name],
                                'created_at' => $comment->created_at?->toDateTimeString(),
                                'content' => $comment->content,
                            ])
                            ->all();

                        return $data;
                    })
                    ->schema([
                        Section::make('Comment')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Author'),
                                TextEntry::make('created_at')
                                    ->label('Created at')
                                    ->dateTime(),
                                TextEntry::make('content')
                                    ->label('Content')
                                    ->columnSpanFull()
                                    ->words(40)
                                    ->tooltip(function (TextEntry $textEntry): ?string {
                                        $state = $textEntry->getState();

                                        return \is_string($state) ? $state : null;
                                    }),
                            ]),
                        Section::make('Replies')
                            ->schema([
                                RepeatableEntry::make('replies')
                                    ->table([
                                        TableColumn::make('Author')->width('160px'),
                                        TableColumn::make('Created at')->width('180px'),
                                        TableColumn::make('Reply'),
                                    ])
                                    ->schema([
                                        TextEntry::make('user.name')
                                            ->label('Author'),
                                        TextEntry::make('created_at')
                                            ->label('Created at')
                                            ->dateTime(),
                                        TextEntry::make('content')
                                            ->label('Reply')
                                            ->words(40)
                                            ->tooltip(function (TextEntry $textEntry): ?string {
                                                $state = $textEntry->getState();

                                                return \is_string($state) ? $state : null;
                                            }),
                                    ]),
                            ]),
                    ]),
            ])
            ->toolbarActions([]);
    }
}
