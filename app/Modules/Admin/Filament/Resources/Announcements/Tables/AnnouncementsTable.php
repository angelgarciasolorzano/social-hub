<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Announcements\Tables;

use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator:id,name'))
            ->deferLoading()
            ->loadingSkeleton()
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => self::typeColor($state)),
                TextColumn::make('audience')
                    ->label('Audience')
                    ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('Deleted user'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(self::typeOptions()),
                SelectFilter::make('audience')
                    ->label('Audience')
                    ->options(self::audienceOptions()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /**
     * The badge color of each announcement type, matching how severe it is.
     */
    public static function typeColor(AnnouncementType $announcementType): string
    {
        return match ($announcementType) {
            AnnouncementType::Info => 'info',
            AnnouncementType::Warning => 'warning',
            AnnouncementType::Critical => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $typeOptions = [];

        foreach (AnnouncementType::cases() as $announcementType) {
            $typeOptions[$announcementType->value] = $announcementType->label();
        }

        return $typeOptions;
    }

    /**
     * @return array<string, string>
     */
    private static function audienceOptions(): array
    {
        $audienceOptions = [];

        foreach (AnnouncementAudience::cases() as $announcementAudience) {
            $audienceOptions[$announcementAudience->value] = $announcementAudience->label();
        }

        return $audienceOptions;
    }
}
