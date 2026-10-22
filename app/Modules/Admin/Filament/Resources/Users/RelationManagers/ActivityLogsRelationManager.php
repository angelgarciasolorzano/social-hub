<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

/**
 * The activity a user performed or received, with the columns and filters of the activity log.
 */
class ActivityLogsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'causedActivityLogs';

    #[Override]
    protected static ?string $title = 'Activity';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', ActivityLog::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table)
            ->query(function (): Builder {
                $model = $this->getOwnerRecord();

                if ($model instanceof User) {
                    return ActivityLog::query()->involvingUser($model);
                }

                throw new LogicException('Activity can only be listed for a user record.');
            })
            ->headerActions([]);
    }
}
