<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Schemas;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use App\Support\Translation\TranslatedText;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

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
                Section::make(TranslatedText::of('admin/activity-log.infolist.activity.heading'))
                    ->description(TranslatedText::of('admin/activity-log.infolist.activity.description'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(TranslatedText::of('admin/common.fields.date'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->dateTime()
                            ->formatStateUsing(static function (TextEntry $component, CarbonInterface $state, ?Model $relatedRecord): string {
                                $date = Date::parse($state)
                                    ->setTimezone($component->getTimezone($state, $relatedRecord));

                                $date->locale(app()->getLocale());

                                return $date->isoFormat('LLL');
                            }),
                        TextEntry::make('log_name')
                            ->label(TranslatedText::of('admin/activity-log.table.log'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::logLabel($state)),
                        TextEntry::make('event')
                            ->label(TranslatedText::of('admin/activity-log.table.event'))
                            ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::label($state)),
                        TextEntry::make('causer_label')
                            ->label(TranslatedText::of('admin/activity-log.table.performed_by'))
                            ->icon(Heroicon::OutlinedUser)
                            ->state(fn (ActivityLog $activityLog): string => self::causerLabel($activityLog))
                            ->url(fn (ActivityLog $activityLog): ?string => self::causerUrl($activityLog)),
                        TextEntry::make('subject_label')
                            ->label(TranslatedText::of('admin/activity-log.table.subject'))
                            ->state(fn (ActivityLog $activityLog): string => self::subjectLabel($activityLog))
                            ->url(fn (ActivityLog $activityLog): ?string => self::subjectUrl($activityLog)),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
                Section::make(TranslatedText::of('admin/activity-log.infolist.details.heading'))
                    ->description(TranslatedText::of('admin/activity-log.infolist.details.description'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        KeyValueEntry::make('details')
                            ->label(TranslatedText::of('admin/activity-log.infolist.details.heading'))
                            ->hiddenLabel()
                            ->state(fn (ActivityLog $activityLog): array => self::details($activityLog))
                            ->placeholder(TranslatedText::of('admin/activity-log.infolist.details.empty')),
                    ]),
                Section::make(TranslatedText::of('admin/activity-log.infolist.request.heading'))
                    ->description(TranslatedText::of('admin/activity-log.infolist.request.description'))
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->schema([
                        TextEntry::make('properties.ip')
                            ->label(TranslatedText::of('admin/activity-log.infolist.request.ip_address'))
                            ->placeholder(TranslatedText::of('admin/activity-log.infolist.request.not_recorded')),
                        TextEntry::make('properties.user_agent')
                            ->label(TranslatedText::of('admin/activity-log.infolist.request.user_agent'))
                            ->placeholder(TranslatedText::of('admin/activity-log.infolist.request.not_recorded')),
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
            \is_bool($propertyValue) => TranslatedText::of($propertyValue
                ? 'admin/activity-log.infolist.values.yes'
                : 'admin/activity-log.infolist.values.no'),
            \is_array($propertyValue) => implode(', ', array_map(self::readableValue(...), $propertyValue)),
            \is_scalar($propertyValue) => (string) $propertyValue,
            default => '—',
        };
    }
}
