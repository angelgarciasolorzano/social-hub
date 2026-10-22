<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Schemas;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ActivityLogInfolist
{
    use DescribesActivityLogParties;

    /**
     * Properties shown in their own section instead of among the details.
     */
    private const array REQUEST_PROPERTIES = ['ip', 'user_agent'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity')
                    ->description('What happened, when, who did it and on what.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime(),
                        TextEntry::make('log_name')
                            ->label('Log')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::logLabel($state)),
                        TextEntry::make('event')
                            ->label('Event')
                            ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::label($state)),
                        TextEntry::make('causer_label')
                            ->label('Performed by')
                            ->icon(Heroicon::OutlinedUser)
                            ->state(fn (ActivityLog $activityLog): string => self::causerLabel($activityLog))
                            ->url(fn (ActivityLog $activityLog): ?string => self::causerUrl($activityLog)),
                        TextEntry::make('subject_label')
                            ->label('Subject')
                            ->state(fn (ActivityLog $activityLog): string => self::subjectLabel($activityLog))
                            ->url(fn (ActivityLog $activityLog): ?string => self::subjectUrl($activityLog)),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
                Section::make('Details')
                    ->description('The references stored with this activity. Content is never recorded.')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        KeyValueEntry::make('details')
                            ->hiddenLabel()
                            ->state(fn (ActivityLog $activityLog): array => self::details($activityLog))
                            ->placeholder('No details recorded'),
                    ]),
                Section::make('Request')
                    ->description('Where the action came from, when it was made through the web.')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->schema([
                        TextEntry::make('properties.ip')
                            ->label('IP address')
                            ->placeholder('Not recorded'),
                        TextEntry::make('properties.user_agent')
                            ->label('User agent')
                            ->placeholder('Not recorded'),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ]);
    }

    /**
     * The stored properties without the request context, as readable text.
     *
     * @return array<string, string>
     */
    private static function details(ActivityLog $activityLog): array
    {
        $details = [];

        foreach ($activityLog->properties?->except(self::REQUEST_PROPERTIES)->all() ?? [] as $propertyKey => $propertyValue) {
            $details[(string) $propertyKey] = self::readableValue($propertyValue);
        }

        return $details;
    }

    /**
     * A stored property value as text: booleans as Yes/No and lists joined by commas.
     */
    private static function readableValue(mixed $propertyValue): string
    {
        return match (true) {
            \is_bool($propertyValue) => $propertyValue ? 'Yes' : 'No',
            \is_array($propertyValue) => implode(', ', array_map(self::readableValue(...), $propertyValue)),
            \is_scalar($propertyValue) => (string) $propertyValue,
            default => '—',
        };
    }
}
