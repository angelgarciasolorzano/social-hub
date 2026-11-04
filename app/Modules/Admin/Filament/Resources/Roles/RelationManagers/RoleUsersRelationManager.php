<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Roles\RelationManagers;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Resources\Roles\Pages\ViewRole;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Models\Role;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

/**
 * The users holding a role, read-only: roles are assigned from the user's detail.
 */
class RoleUsersRelationManager extends RelationManager
{
    use DescribesAccountStatus;

    #[Override]
    protected static string $relationship = 'users';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/roles.relations.users.title');
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
            && Gate::forUser($authenticatedUser)->allows('viewAny', User::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(TranslatedText::of('admin/roles.relations.users.name'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (User $record): ?string => UserResource::canView($record)
                        ? UserResource::getUrl('view', ['record' => $record])
                        : null),
                TextColumn::make('email')
                    ->label(TranslatedText::of('admin/roles.relations.users.email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/common.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
            ])
            ->emptyStateHeading(TranslatedText::of('admin/roles.relations.users.empty'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
