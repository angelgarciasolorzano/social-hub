<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Auth\Models\TrustedDevice;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
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

class TrustedDevicesRelationManager extends RelationManager
{
    private const string ACTIVE_STATUS = 'Active';

    private const string EXPIRED_STATUS = 'Expired';

    private const string REVOKED_STATUS = 'Revoked';

    #[Override]
    protected static string $relationship = 'trustedDevices';

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
                TextColumn::make('name')
                    ->label('Device')
                    ->placeholder('Unknown device'),
                TextColumn::make('device_summary')
                    ->label('Browser / OS')
                    ->state(fn (TrustedDevice $trustedDevice): string => $this->getDeviceSummary($trustedDevice)),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        self::ACTIVE_STATUS => 'success',
                        self::EXPIRED_STATUS => 'warning',
                        self::REVOKED_STATUS => 'gray',
                        default => 'gray',
                    })
                    ->state(fn (TrustedDevice $trustedDevice): string => $this->getStatus($trustedDevice)),
            ])
            ->defaultSort('id', 'desc')
            ->query(function (): Builder {
                $model = $this->getOwnerRecord();

                throw_unless($model instanceof User, LogicException::class, 'Trusted devices can only be listed for a user record.');

                return $model->trustedDevices()
                    ->getQuery()
                    ->withTrashed()
                    ->select([
                        'id',
                        'user_id',
                        'name',
                        'browser',
                        'browser_version',
                        'os_name',
                        'os_version',
                        'last_used_at',
                        'expires_at',
                        'deleted_at',
                    ]);
            })
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private function getDeviceSummary(TrustedDevice $trustedDevice): string
    {
        $browser = implode(' ', array_filter([
            $trustedDevice->browser,
            $trustedDevice->browser_version,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
        $operatingSystem = implode(' ', array_filter([
            $trustedDevice->os_name,
            $trustedDevice->os_version,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
        $summary = implode(' · ', array_filter([
            $browser,
            $operatingSystem,
        ], static fn (string $part): bool => $part !== ''));

        return $summary !== '' ? $summary : 'Unknown';
    }

    private function getStatus(TrustedDevice $trustedDevice): string
    {
        if ($trustedDevice->deleted_at !== null) {
            return self::REVOKED_STATUS;
        }

        return $trustedDevice->expires_at->isFuture()
            ? self::ACTIVE_STATUS
            : self::EXPIRED_STATUS;
    }
}
