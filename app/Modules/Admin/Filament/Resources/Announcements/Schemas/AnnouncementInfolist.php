<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Schemas;

use App\Modules\Admin\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class AnnouncementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Content')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('body')
                            ->label('Message')
                            ->state(fn (Announcement $announcement): HtmlString => new HtmlString($announcement->safeBodyHtml()))
                            ->prose(),
                        SpatieMediaLibraryImageEntry::make('image')
                            ->label('Image')
                            ->collection(Announcement::IMAGE_MEDIA_COLLECTION)
                            ->placeholder('No image'),
                    ]),
                Section::make('Display')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ])
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                            ->color(fn (AnnouncementType $state): string => AnnouncementsTable::typeColor($state)),
                        TextEntry::make('audience')
                            ->label('Audience')
                            ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                        TextEntry::make('display')
                            ->label('Shown as')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (AnnouncementDisplay $state): string => $state->label())
                            ->icon(fn (AnnouncementDisplay $state): Heroicon => AnnouncementsTable::displayIcon($state)),
                        IconEntry::make('is_dismissible')
                            ->label('Users can dismiss it')
                            ->boolean(),
                        TextEntry::make('starts_at')
                            ->label('Starts at')
                            ->dateTime(),
                        TextEntry::make('ends_at')
                            ->label('Ends at')
                            ->dateTime()
                            ->placeholder('No end date'),
                    ]),
                Section::make('Button')
                    ->icon(Heroicon::OutlinedCursorArrowRays)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        TextEntry::make('button_label')
                            ->label('Button text')
                            ->placeholder('No button'),
                        TextEntry::make('button_url')
                            ->label('Button link')
                            ->placeholder('No button'),
                    ]),
                Section::make('History')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ])
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Created by')
                            ->placeholder('Deleted user'),
                        TextEntry::make('created_at')
                            ->label('Created at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Updated at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
