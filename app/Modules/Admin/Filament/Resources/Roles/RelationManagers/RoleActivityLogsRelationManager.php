<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\RelationManagers;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Roles\Tables\RoleActivityLogsTable;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

/**
 * The history of a role: its own changes, its grants and revocations to users and the bootstrap that
 * gave it, described in readable sentences and grouped by day.
 */
class RoleActivityLogsRelationManager extends RelationManager
{
    /**
     * Spatie's role has no relationship to the activity log, so `getRelationship()` builds it; the
     * name only identifies the tab.
     */
    #[Override]
    protected static string $relationship = 'activityLogs';

    /**
     * The names of the source roles of the duplicates on the current page, loaded once per request.
     *
     * @var array<int, string>|null
     */
    private ?array $sourceRoleNamesById = null;

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
     * The activity whose subject is this role, as a relationship built on the owner record; the table
     * lists the wider `Role::activityLogQuery()`.
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
        return RoleActivityLogsTable::configure($table, fn (): array => $this->sourceRoleNamesById())
            ->query(function (): Builder {
                $model = $this->getOwnerRecord();

                if ($model instanceof Role) {
                    return $model->activityLogQuery();
                }

                throw new LogicException('History can only be listed for a role record.');
            });
    }

    /**
     * The shown names of the roles the duplicates on this page were copied from, in one query.
     *
     * @return array<int, string>
     */
    private function sourceRoleNamesById(): array
    {
        if ($this->sourceRoleNamesById !== null) {
            return $this->sourceRoleNamesById;
        }

        $tableRecords = $this->getTableRecords();
        $sourceRoleIds = [];

        foreach ($tableRecords instanceof Collection ? $tableRecords->all() : $tableRecords->items() as $tableRecord) {
            $sourceRoleId = $tableRecord instanceof ActivityLog ? $tableRecord->getProperty('source_role_id') : null;

            if (\is_int($sourceRoleId)) {
                $sourceRoleIds[] = $sourceRoleId;
            }
        }

        $sourceRoles = $sourceRoleIds === []
            ? new EloquentCollection
            : Role::query()->whereKey(array_unique($sourceRoleIds))->get()->keyBy('id');
        $this->sourceRoleNamesById = [];

        foreach ($sourceRoleIds as $sourceRoleId) {
            $sourceRole = $sourceRoles->get($sourceRoleId);

            if ($sourceRole instanceof Role) {
                $this->sourceRoleNamesById[$sourceRoleId] = $sourceRole->displayName();
            }
        }

        return $this->sourceRoleNamesById;
    }
}
