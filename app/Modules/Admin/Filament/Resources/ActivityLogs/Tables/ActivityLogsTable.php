<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ActivityLogs\Tables;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use App\User\Models\User;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    use DescribesActivityLogParties;

    public static function configure(Table $table, bool $deferLoading = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->deferLoading($deferLoading)
            ->loadingSkeleton()
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::logLabel($state)),
                TextColumn::make('event')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::label($state)),
                TextColumn::make('causer_label')
                    ->label('Performed by')
                    ->state(fn (ActivityLog $activityLog): string => self::causerLabel($activityLog))
                    ->url(fn (ActivityLog $activityLog): ?string => self::causerUrl($activityLog)),
                TextColumn::make('subject_label')
                    ->label('Subject')
                    ->state(fn (ActivityLog $activityLog): string => self::subjectLabel($activityLog))
                    ->url(fn (ActivityLog $activityLog): ?string => self::subjectUrl($activityLog)),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log')
                    ->options(ActivityLogEventCatalog::logOptions()),
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(ActivityLogEventCatalog::eventOptions())
                    ->searchable(),
                Filter::make('causer')
                    ->label('Performed by')
                    ->schema([
                        Select::make('causer_id')
                            ->label('Performed by')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->whereLike('name', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value)
                                ? User::query()->find((int) $value)?->name
                                : null),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filterByCauser($query, $data)),
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('created_from')->label('From'),
                        DatePicker::make('created_until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '>=', $date)
                                : $builder,
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $builder, mixed $date): Builder => (\is_string($date) || $date instanceof DateTimeInterface)
                                ? $builder->whereDate('created_at', '<=', $date)
                                : $builder,
                        )),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * Keep the activities performed by the user chosen in the filter, if any.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  array<mixed>  $data
     * @return Builder<ActivityLog>
     */
    private static function filterByCauser(Builder $query, array $data): Builder
    {
        $causerId = $data['causer_id'] ?? null;

        if (! is_numeric($causerId)) {
            return $query;
        }

        return $query
            ->where('causer_type', User::MORPH_NAME)
            ->where('causer_id', (int) $causerId);
    }
}
