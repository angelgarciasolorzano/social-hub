<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Schemas;

use App\Modules\Post\Models\Post;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post information')
                    ->description('Author and publication history for this post.')
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Author')
                            ->icon(Heroicon::OutlinedUser),
                        TextEntry::make('created_at')
                            ->label('Created at')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Updated at')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->dateTime(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
                Section::make('Post content')
                    ->description('The complete message and image shared in this post.')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('content')
                            ->label('Message')
                            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                            ->prose()
                            ->columnSpanFull(),
                        ImageEntry::make('post_image')
                            ->label('Post image')
                            ->state(function (Post $record): ?string {
                                $imageUrl = $record->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION);

                                return filled($imageUrl) ? url($imageUrl) : null;
                            })
                            ->defaultImageUrl(asset('default-cover.svg'))
                            ->imageHeight(320)
                            ->columnSpanFull()
                            ->alt('Post image'),
                    ]),
            ]);
    }
}
