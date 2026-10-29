<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Posts\Schemas;

use App\Modules\Post\Models\Post;
use App\Support\Translation\TranslatedText;
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
                Section::make(TranslatedText::of('admin/posts.detail.information'))
                    ->description(TranslatedText::of('admin/posts.detail.information_description'))
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(TranslatedText::of('admin/posts.detail.author'))
                            ->icon(Heroicon::OutlinedUser),
                        TextEntry::make('created_at')
                            ->label(TranslatedText::of('admin/posts.detail.created_at'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(TranslatedText::of('admin/posts.detail.updated_at'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->dateTime(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
                Section::make(TranslatedText::of('admin/posts.detail.content'))
                    ->description(TranslatedText::of('admin/posts.detail.content_description'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('content')
                            ->label(TranslatedText::of('admin/posts.detail.message'))
                            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                            ->prose()
                            ->columnSpanFull(),
                        ImageEntry::make('post_image')
                            ->label(TranslatedText::of('admin/posts.detail.image'))
                            ->state(function (Post $record): ?string {
                                $imageUrl = $record->getFirstMediaUrl(Post::POSTS_IMAGES_MEDIA_COLLECTION);

                                return filled($imageUrl) ? url($imageUrl) : null;
                            })
                            ->defaultImageUrl(asset('default-cover.svg'))
                            ->imageHeight(320)
                            ->columnSpanFull()
                            ->alt(TranslatedText::of('admin/posts.detail.image_alt')),
                    ]),
            ]);
    }
}
