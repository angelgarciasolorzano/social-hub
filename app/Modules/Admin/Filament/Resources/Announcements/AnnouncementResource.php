<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements;

use App\Modules\Admin\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Modules\Admin\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Admin\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use App\Modules\Admin\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Modules\Announcement\Models\Announcement;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

/**
 * System announcements shown as banners in the application. Access is decided by AnnouncementPolicy.
 */
class AnnouncementResource extends Resource
{
    #[Override]
    protected static ?string $model = Announcement::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    #[Override]
    protected static ?string $modelLabel = 'announcement';

    #[Override]
    protected static ?string $pluralModelLabel = 'announcements';

    #[Override]
    protected static ?string $navigationLabel = 'Announcements';

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
        ];
    }
}
