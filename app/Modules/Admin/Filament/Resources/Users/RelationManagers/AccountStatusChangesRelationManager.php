<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
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
    protected static ?string $title = 'Status history';

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
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap(),
                TextColumn::make('changedBy.name')
                    ->label('Changed by')
                    ->placeholder('Deleted user'),
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
