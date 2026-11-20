<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\Support\Database\LikePattern;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

class FriendshipsRelationManager extends RelationManager
{
    /**
     * Status keys of a row seen from the owner; each one is also its key under
     * `admin/users.relations.friendships.statuses`.
     */
    private const string ACCEPTED_STATUS = 'accepted';

    private const string PENDING_SENT_STATUS = 'pending_sent';

    private const string PENDING_RECEIVED_STATUS = 'pending_received';

    private const string SINCE_COLUMN = 'since';

    /**
     * Direction keys of a request seen from the owner; each one is also its key under
     * `admin/users.relations.friendships.filters`.
     */
    private const string SENT_DIRECTION = 'sent';

    private const string RECEIVED_DIRECTION = 'received';

    #[Override]
    protected static string $relationship = 'sentFriendRequests';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return TranslatedText::of('admin/users.relations.friendships.title');
    }

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof User || $pageClass !== ViewUser::class) {
            return false;
        }

        $authenticatedUser = Filament::auth()->user();

        return $authenticatedUser instanceof User
            && Gate::forUser($authenticatedUser)->allows('view', $ownerRecord)
            && Gate::forUser($authenticatedUser)->allows('viewAny', Friendship::class);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->loadingSkeleton()
            ->query(fn (): Builder => Friendship::query()
                ->involving($this->getOwner())
                ->select(['id', 'requester_id', 'receiver_id', 'status', 'accepted_at', 'created_at'])
                ->with([
                    'requester:id,name',
                    'receiver:id,name',
                ]))
            ->columns([
                TextColumn::make('other_user')
                    ->label(TranslatedText::of('admin/users.relations.friendships.other_user'))
                    ->state(fn (Friendship $friendship): string => $this->getOtherUser($friendship)->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchByOtherUserName($query, $search))
                    ->url(function (Friendship $friendship): ?string {
                        $otherUser = $this->getOtherUser($friendship);

                        return UserResource::canView($otherUser)
                            ? UserResource::getUrl('view', ['record' => $otherUser])
                            : null;
                    }),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/users.relations.friendships.status'))
                    ->badge()
                    ->state(fn (Friendship $friendship): string => $this->getStatus($friendship))
                    ->formatStateUsing(fn (string $state): string => TranslatedText::of('admin/users.relations.friendships.statuses.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        self::ACCEPTED_STATUS => 'success',
                        self::PENDING_SENT_STATUS => 'info',
                        self::PENDING_RECEIVED_STATUS => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('sent_by')
                    ->label(TranslatedText::of('admin/users.relations.friendships.sent_by'))
                    ->state(fn (Friendship $friendship): string => $friendship->requester->name),
                TextColumn::make(self::SINCE_COLUMN)
                    ->label(TranslatedText::of('admin/users.relations.friendships.since'))
                    ->state(fn (Friendship $friendship): ?CarbonImmutable => $friendship->accepted_at ?? $friendship->created_at)
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $direction === 'asc'
                        ? $query->orderByRaw('COALESCE(accepted_at, created_at) asc')->orderBy('id')
                        : $query->orderByRaw('COALESCE(accepted_at, created_at) desc')->orderByDesc('id')),
            ])
            ->defaultSort(self::SINCE_COLUMN, 'desc')
            ->searchPlaceholder(TranslatedText::of('admin/users.relations.friendships.search'))
            ->filters([
                SelectFilter::make('status')
                    ->label(TranslatedText::of('admin/users.relations.friendships.filters.status'))
                    ->options([
                        self::ACCEPTED_STATUS => TranslatedText::of('admin/users.relations.friendships.statuses.accepted'),
                        self::PENDING_SENT_STATUS => TranslatedText::of('admin/users.relations.friendships.statuses.pending_sent'),
                        self::PENDING_RECEIVED_STATUS => TranslatedText::of('admin/users.relations.friendships.statuses.pending_received'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByStatus($query, is_string($data['value'] ?? null) ? $data['value'] : null)),
                SelectFilter::make('direction')
                    ->label(TranslatedText::of('admin/users.relations.friendships.filters.direction'))
                    ->options([
                        self::SENT_DIRECTION => TranslatedText::of('admin/users.relations.friendships.filters.sent'),
                        self::RECEIVED_DIRECTION => TranslatedText::of('admin/users.relations.friendships.filters.received'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByDirection($query, is_string($data['value'] ?? null) ? $data['value'] : null)),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    private function getOwner(): User
    {
        $model = $this->getOwnerRecord();

        if ($model instanceof User) {
            return $model;
        }

        throw new LogicException('Friendships can only be listed for a user record.');
    }

    private function getOtherUser(Friendship $friendship): User
    {
        return $friendship->requester_id === $this->getOwner()->id
            ? $friendship->receiver
            : $friendship->requester;
    }

    private function getStatus(Friendship $friendship): string
    {
        if ($friendship->isAccepted()) {
            return self::ACCEPTED_STATUS;
        }

        return $friendship->requester_id === $this->getOwner()->id
            ? self::PENDING_SENT_STATUS
            : self::PENDING_RECEIVED_STATUS;
    }

    /**
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    private function filterByStatus(Builder $query, ?string $status): Builder
    {
        $ownerId = $this->getOwner()->id;

        return match ($status) {
            self::ACCEPTED_STATUS => $query->where('status', FriendshipStatus::Accepted),
            self::PENDING_SENT_STATUS => $query->where('status', FriendshipStatus::Pending)->where('requester_id', $ownerId),
            self::PENDING_RECEIVED_STATUS => $query->where('status', FriendshipStatus::Pending)->where('receiver_id', $ownerId),
            default => $query,
        };
    }

    /**
     * Who sent the request, seen from the owner: the requests the owner sent or the ones they received.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    private function filterByDirection(Builder $query, ?string $direction): Builder
    {
        $ownerId = $this->getOwner()->id;

        return match ($direction) {
            self::SENT_DIRECTION => $query->where('requester_id', $ownerId),
            self::RECEIVED_DIRECTION => $query->where('receiver_id', $ownerId),
            default => $query,
        };
    }

    /**
     * The rows whose other user's name starts with the term; the owner's own name never matches, and
     * `LIKE` wildcards in the term are searched literally.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    private function searchByOtherUserName(Builder $query, string $search): Builder
    {
        $ownerId = $this->getOwner()->id;
        $pattern = LikePattern::startsWith($search);

        return $query->where(static fn (Builder $builder): Builder => $builder
            ->where(static fn (Builder $sentRequests): Builder => $sentRequests
                ->where('requester_id', $ownerId)
                ->whereHas('receiver', static fn (Builder $receiver): Builder => $receiver->where('name', 'like', $pattern)))
            ->orWhere(static fn (Builder $receivedRequests): Builder => $receivedRequests
                ->where('receiver_id', $ownerId)
                ->whereHas('requester', static fn (Builder $requester): Builder => $requester->where('name', 'like', $pattern))));
    }
}
