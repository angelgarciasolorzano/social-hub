<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\Tables;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Support\ActivityLogEventCatalog;
use App\Modules\Admin\Enums\RoleActivityChangeType;
use App\Modules\Admin\Filament\Concerns\DescribesActivityLogParties;
use App\Modules\Admin\Filament\Concerns\DescribesRoleActivity;
use App\Modules\Admin\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Support\Translation\TranslatedText;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The history of one role: what changed, as a translated sentence, who did it and when, grouped by day
 * and filtered by kind of change, author and date.
 */
class RoleActivityLogsTable
{
    use DescribesActivityLogParties;
    use DescribesRoleActivity;

    /**
     * Configure the history; `$sourceRoleNames` returns the names of the duplicated roles' sources on the
     * page, loaded once.
     *
     * @param  Closure(): array<int, string>  $sourceRoleNames
     */
    public static function configure(Table $table, Closure $sourceRoleNames): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->loadingSkeleton()
            ->columns([
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/common.fields.date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('change_type')
                    ->label(TranslatedText::of('admin/roles.relations.history.columns.change_type'))
                    ->badge()
                    ->state(fn (ActivityLog $activityLog): ?string => RoleActivityChangeType::forEvent($activityLog->event)?->label())
                    ->color(fn (ActivityLog $activityLog): string => match (RoleActivityChangeType::forEvent($activityLog->event)) {
                        RoleActivityChangeType::Permissions => 'info',
                        RoleActivityChangeType::Users => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('detail')
                    ->label(TranslatedText::of('admin/roles.relations.history.columns.detail'))
                    ->state(fn (ActivityLog $activityLog): string => self::roleActivityDetail($activityLog, $sourceRoleNames()))
                    ->wrap(),
                TextColumn::make('causer_label')
                    ->label(TranslatedText::of('admin/activity-log.table.performed_by'))
                    ->state(fn (ActivityLog $activityLog): string => self::causerLabel($activityLog))
                    ->url(fn (ActivityLog $activityLog): ?string => self::causerUrl($activityLog)),
                TextColumn::make('event')
                    ->label(TranslatedText::of('admin/activity-log.table.event'))
                    ->formatStateUsing(fn (string $state): string => ActivityLogEventCatalog::label($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject_label')
                    ->label(TranslatedText::of('admin/activity-log.table.subject'))
                    ->state(fn (ActivityLog $activityLog): string => self::subjectLabel($activityLog))
                    ->url(fn (ActivityLog $activityLog): ?string => self::subjectUrl($activityLog))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('change_type')
                    ->label(TranslatedText::of('admin/roles.relations.history.filters.change_type'))
                    ->multiple()
                    ->options(self::changeTypeOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::filterByChangeType($query, $data)),
                ActivityLogsTable::causerFilter(),
                ActivityLogsTable::dateRangeFilter(),
            ])
            ->groups([
                Group::make('created_at')
                    ->label(TranslatedText::of('admin/roles.relations.history.group.day'))
                    ->date()
                    ->collapsible(),
            ])
            ->defaultGroup('created_at', 'desc')
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye)
                    ->modal(false)
                    ->url(fn (ActivityLog $activityLog): string => ActivityLogResource::getUrl('view', ['record' => $activityLog])),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    /**
     * The kinds of change, by value, with their translated names.
     *
     * @return array<string, string>
     */
    private static function changeTypeOptions(): array
    {
        $options = [];

        foreach (RoleActivityChangeType::cases() as $roleActivityChangeType) {
            $options[$roleActivityChangeType->value] = $roleActivityChangeType->label();
        }

        return $options;
    }

    /**
     * Keep the activity of the kinds of change chosen in the filter, if any.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  array<mixed>  $data
     * @return Builder<ActivityLog>
     */
    private static function filterByChangeType(Builder $query, array $data): Builder
    {
        $events = [];

        foreach (\is_array($data['values'] ?? null) ? $data['values'] : [] as $value) {
            $roleActivityChangeType = \is_string($value) ? RoleActivityChangeType::tryFrom($value) : null;

            foreach ($roleActivityChangeType?->events() ?? [] as $activityLogAdminEvent) {
                $events[] = $activityLogAdminEvent->value;
            }
        }

        return $events === [] ? $query : $query->whereIn('event', $events);
    }
}
