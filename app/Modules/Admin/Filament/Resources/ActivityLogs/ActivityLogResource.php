<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Support\Translation\TranslatedText;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

/**
 * Read-only view of the activity log. Access is decided by ActivityLogPolicy.
 */
class ActivityLogResource extends Resource
{
    #[Override]
    protected static ?string $model = ActivityLog::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    #[Override]
    public static function getModelLabel(): string
    {
        return TranslatedText::of('admin/activity-log.resource.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return TranslatedText::of('admin/activity-log.resource.plural_label');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return TranslatedText::of('admin/activity-log.resource.navigation_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
