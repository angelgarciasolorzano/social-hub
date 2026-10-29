<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\Schemas;

use App\Support\Translation\TranslatedText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CommentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(TranslatedText::of('admin/comments.infolist.details.heading'))
                    ->description(TranslatedText::of('admin/comments.infolist.details.description'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(TranslatedText::of('admin/comments.fields.author'))
                            ->icon(Heroicon::OutlinedUser),
                        TextEntry::make('created_at')
                            ->label(TranslatedText::of('admin/comments.fields.created_at'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                Section::make(TranslatedText::of('admin/comments.infolist.content.heading'))
                    ->description(TranslatedText::of('admin/comments.infolist.content.description'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('content')
                            ->label(TranslatedText::of('admin/comments.fields.message'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
