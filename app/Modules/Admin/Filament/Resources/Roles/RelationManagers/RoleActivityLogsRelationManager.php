<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\RelationManagers;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\Permission\Models\Role;

/**
 * The activity recorded with a role as its subject (its permission changes), with the columns and
 * filters of the activity log.
 */
class RoleActivityLogsRelationManager extends RelationManager
{
    /**
     * Spatie's role has no relationship to the activity log, so `getRelationship()` builds it; the
     * name only identifies the tab.
     */
    #[Override]
    protected static string $relationship = 'activityLogs';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/roles.relations.history.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Role || $pageClass !== ViewRole::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', ActivityLog::class);
    }

    /**
     * The activity whose subject is this role, as a relationship built on the owner record.
     *
     * @return MorphMany<ActivityLog, Model>
     */
    #[Override]
    public function getRelationship(): MorphMany
    {
        return $this->getOwnerRecord()->morphMany(ActivityLog::class, 'subject');
    }

    #[Override]
    public function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table, deferLoading: false)
            ->headerActions([]);
    }
}
