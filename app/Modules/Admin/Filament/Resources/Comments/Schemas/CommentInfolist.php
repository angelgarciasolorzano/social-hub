<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Comments\Schemas;

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
                Section::make('Comment details')
                    ->description('Author and publication details for this comment.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Author')
                            ->icon(Heroicon::OutlinedUser),
                        TextEntry::make('created_at')
                            ->label('Created at')
                            ->dateTime(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                Section::make('Comment content')
                    ->description('The complete message in this conversation.')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('content')
                            ->label('Message')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
