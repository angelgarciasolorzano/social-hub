<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

class AccountStatusChangesRelationManager extends RelationManager
{
    use DescribesAccountStatus;

    #[Override]
    protected static string $relationship = 'accountStatusChanges';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/users.relations.status_history.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->columns([
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/common.fields.date'))
                    ->dateTime(),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/common.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
                TextColumn::make('reason')
                    ->label(TranslatedText::of('admin/common.fields.reason'))
                    ->wrap(),
                TextColumn::make('changedBy.name')
                    ->label(TranslatedText::of('admin/users.relations.status_history.changed_by'))
                    ->placeholder(TranslatedText::of('admin/common.deleted_user')),
            ])
            ->query(function (): Builder {
                $model = $this->getOwnerRecord();

                throw_unless($model instanceof User, LogicException::class, 'The status history can only be listed for a user record.');

                return $model->accountStatusChanges()
                    ->getQuery()
                    ->with('changedBy:id,name');
            })
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
