<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Schemas;

use App\Modules\Admin\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Support\Translation\TranslatedText;
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
                Section::make(TranslatedText::of('admin/announcements.sections.content'))
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->schema([
                        TextEntry::make('title')
                            ->label(TranslatedText::of('admin/announcements.fields.title')),
                        TextEntry::make('body')
                            ->label(TranslatedText::of('admin/announcements.fields.message'))
                            ->state(fn (Announcement $announcement): HtmlString => new HtmlString($announcement->safeBodyHtml()))
                            ->prose(),
                        SpatieMediaLibraryImageEntry::make('image')
                            ->label(TranslatedText::of('admin/announcements.fields.image'))
                            ->collection(Announcement::IMAGE_MEDIA_COLLECTION)
                            ->placeholder(TranslatedText::of('admin/announcements.placeholders.no_image')),
                    ]),
                Section::make(TranslatedText::of('admin/announcements.sections.display'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ])
                    ->schema([
                        TextEntry::make('type')
                            ->label(TranslatedText::of('admin/announcements.fields.type'))
                            ->badge()
                            ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                            ->color(fn (AnnouncementType $state): string => AnnouncementsTable::typeColor($state)),
                        TextEntry::make('audience')
                            ->label(TranslatedText::of('admin/announcements.fields.audience'))
                            ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                        TextEntry::make('display')
                            ->label(TranslatedText::of('admin/announcements.fields.display'))
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (AnnouncementDisplay $state): string => $state->label())
                            ->icon(fn (AnnouncementDisplay $state): Heroicon => AnnouncementsTable::displayIcon($state)),
                        IconEntry::make('is_dismissible')
                            ->label(TranslatedText::of('admin/announcements.fields.is_dismissible'))
                            ->boolean(),
                        TextEntry::make('starts_at')
                            ->label(TranslatedText::of('admin/announcements.fields.starts_at'))
                            ->dateTime(),
                        TextEntry::make('ends_at')
                            ->label(TranslatedText::of('admin/announcements.fields.ends_at'))
                            ->dateTime()
                            ->placeholder(TranslatedText::of('admin/announcements.placeholders.no_end_date')),
                    ]),
                Section::make(TranslatedText::of('admin/announcements.sections.button'))
                    ->icon(Heroicon::OutlinedCursorArrowRays)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        TextEntry::make('button_label')
                            ->label(TranslatedText::of('admin/announcements.fields.button_text'))
                            ->placeholder(TranslatedText::of('admin/announcements.placeholders.no_button')),
                        TextEntry::make('button_url')
                            ->label(TranslatedText::of('admin/announcements.fields.button_link'))
                            ->placeholder(TranslatedText::of('admin/announcements.placeholders.no_button')),
                    ]),
                Section::make(TranslatedText::of('admin/announcements.sections.history'))
                    ->icon(Heroicon::OutlinedUser)
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ])
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label(TranslatedText::of('admin/announcements.fields.created_by'))
                            ->placeholder(TranslatedText::of('admin/announcements.placeholders.deleted_user')),
                        TextEntry::make('created_at')
                            ->label(TranslatedText::of('admin/announcements.fields.created_at'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(TranslatedText::of('admin/announcements.fields.updated_at'))
                            ->dateTime(),
                    ]),
            ]);
    }
}
