<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users\RelationManagers;

use App\Modules\Admin\Filament\Concerns\DescribesAccountStatus;
use App\Modules\Admin\Filament\Concerns\ReadsAggregateCounts;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\Support\Database\LikePattern;
use App\Support\Translation\TranslatedText;
use App\User\Enums\AccountStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Override;

class FriendshipsRelationManager extends RelationManager
{
    use DescribesAccountStatus;
    use ReadsAggregateCounts;

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
                    'requester:id,name,email,status,last_login_at',
                    'requester.media',
                    'receiver:id,name,email,status,last_login_at',
                    'receiver.media',
                ]))
            ->description(fn (): ?string => $this->getSummary())
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label(TranslatedText::of('admin/users.relations.friendships.profile_picture'))
                    ->state(fn (Friendship $friendship): string => url(
                        $this->getOtherUser($friendship)->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
                    ))
                    ->defaultImageUrl(asset(User::DEFAULT_PROFILE_PICTURE_PATH))
                    ->imageSize(40)
                    ->circular()
                    ->alt(fn (Friendship $friendship): string => TranslatedText::of('admin/users.relations.friendships.profile_picture_alt', [
                        'name' => $this->getOtherUser($friendship)->name,
                    ])),
                TextColumn::make('other_user')
                    ->label(TranslatedText::of('admin/users.relations.friendships.other_user'))
                    ->state(fn (Friendship $friendship): string => $this->getOtherUser($friendship)->name)
                    ->description(fn (Friendship $friendship): string => $this->getOtherUser($friendship)->email)
                    ->weight(FontWeight::Medium)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchByOtherUserName($query, $search))
                    ->url(function (Friendship $friendship): ?string {
                        $otherUser = $this->getOtherUser($friendship);

                        return UserResource::canView($otherUser)
                            ? UserResource::getUrl('view', ['record' => $otherUser])
                            : null;
                    }),
                TextColumn::make('account')
                    ->label(TranslatedText::of('admin/users.relations.friendships.account'))
                    ->badge()
                    ->state(fn (Friendship $friendship): AccountStatus => $this->getOtherUser($friendship)->status)
                    ->formatStateUsing(fn (AccountStatus $state): string => self::accountStatusLabel($state))
                    ->color(fn (AccountStatus $state): string => self::accountStatusColor($state)),
                TextColumn::make('status')
                    ->label(TranslatedText::of('admin/users.relations.friendships.status'))
                    ->badge()
                    ->state(fn (Friendship $friendship): string => $this->getStatus($friendship))
                    ->formatStateUsing(fn (string $state): string => TranslatedText::of('admin/users.relations.friendships.statuses.'.$state))
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        self::ACCEPTED_STATUS => Heroicon::OutlinedUserGroup,
                        self::PENDING_SENT_STATUS => Heroicon::OutlinedArrowUpRight,
                        default => Heroicon::OutlinedArrowDownLeft,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        self::ACCEPTED_STATUS => 'success',
                        self::PENDING_SENT_STATUS => 'info',
                        self::PENDING_RECEIVED_STATUS => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('sent_by')
                    ->label(TranslatedText::of('admin/users.relations.friendships.sent_by'))
                    ->state(fn (Friendship $friendship): string => $friendship->requester->name)
                    ->toggleable(),
                TextColumn::make(self::SINCE_COLUMN)
                    ->label(TranslatedText::of('admin/users.relations.friendships.since'))
                    ->state(fn (Friendship $friendship): ?CarbonImmutable => $this->getSince($friendship))
                    ->dateTime()
                    ->description(fn (Friendship $friendship): ?string => $this->getSince($friendship)?->diffForHumans())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $direction === 'asc'
                        ? $query->orderByRaw('COALESCE(accepted_at, created_at) asc')->orderBy('id')
                        : $query->orderByRaw('COALESCE(accepted_at, created_at) desc')->orderByDesc('id')),
                TextColumn::make('created_at')
                    ->label(TranslatedText::of('admin/users.relations.friendships.requested_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('accepted_at')
                    ->label(TranslatedText::of('admin/users.relations.friendships.accepted_at'))
                    ->dateTime()
                    ->placeholder(TranslatedText::of('admin/users.relations.friendships.not_accepted'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login')
                    ->label(TranslatedText::of('admin/users.relations.friendships.last_login'))
                    ->state(fn (Friendship $friendship): ?CarbonImmutable => $this->getOtherUser($friendship)->last_login_at)
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(TranslatedText::of('admin/common.never'))
                    ->toggleable(),
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
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByStatus($query, \is_string($data['value'] ?? null) ? $data['value'] : null)),
                SelectFilter::make('direction')
                    ->label(TranslatedText::of('admin/users.relations.friendships.filters.direction'))
                    ->options([
                        self::SENT_DIRECTION => TranslatedText::of('admin/users.relations.friendships.filters.sent'),
                        self::RECEIVED_DIRECTION => TranslatedText::of('admin/users.relations.friendships.filters.received'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByDirection($query, \is_string($data['value'] ?? null) ? $data['value'] : null)),
                SelectFilter::make('account')
                    ->label(TranslatedText::of('admin/users.relations.friendships.filters.account'))
                    ->options(self::accountStatusOptions())
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByOtherUserAccount($query, \is_string($data['value'] ?? null) ? AccountStatus::tryFrom($data['value']) : null)),
                Filter::make('date')
                    ->label(TranslatedText::of('admin/users.relations.friendships.filters.date'))
                    ->schema([
                        DatePicker::make('date_from')->label(TranslatedText::of('admin/users.relations.friendships.filters.date_from')),
                        DatePicker::make('date_until')->label(TranslatedText::of('admin/users.relations.friendships.filters.date_until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->filterBySince(
                        $query,
                        $this->filterDate($data['date_from'] ?? null),
                        $this->filterDate($data['date_until'] ?? null),
                    ))
                    ->indicateUsing(fn (array $data): array => $this->dateIndicators($this->filterDate($data['date_from'] ?? null), $this->filterDate($data['date_until'] ?? null))),
            ])
            ->emptyStateHeading(TranslatedText::of('admin/users.relations.friendships.empty.heading'))
            ->emptyStateDescription(TranslatedText::of('admin/users.relations.friendships.empty.description'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * The owner's friends and the requests they received and sent, in one query; null without rows.
     */
    private function getSummary(): ?string
    {
        $ownerId = $this->getOwner()->id;
        $counts = self::aggregateCounts(
            Friendship::query()
                ->involving($this->getOwner())
                ->selectRaw(
                    'count(*) as total, '
                    .'coalesce(sum(case when status = ? then 1 else 0 end), 0) as friends, '
                    .'coalesce(sum(case when status = ? and receiver_id = ? then 1 else 0 end), 0) as received, '
                    .'coalesce(sum(case when status = ? and requester_id = ? then 1 else 0 end), 0) as sent',
                    [
                        FriendshipStatus::Accepted->value,
                        FriendshipStatus::Pending->value,
                        $ownerId,
                        FriendshipStatus::Pending->value,
                        $ownerId,
                    ],
                ),
            ['total', 'friends', 'received', 'sent'],
        );

        if ($counts['total'] === 0) {
            return null;
        }

        return TranslatedText::of('admin/users.relations.friendships.summary', [
            'friends' => $counts['friends'],
            'received' => $counts['received'],
            'sent' => $counts['sent'],
        ]);
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
        $pattern = LikePattern::startsWith($search);

        return $this->whereOtherUser($query, static fn (Builder $otherUser): Builder => $otherUser->where('name', 'like', $pattern));
    }

    /**
     * The date a row counts from: when it was accepted, or when the request was sent while pending.
     */
    private function getSince(Friendship $friendship): ?CarbonImmutable
    {
        return $friendship->accepted_at ?? $friendship->created_at;
    }

    /**
     * The rows whose other user has the given account status; the owner's own status never counts.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    private function filterByOtherUserAccount(Builder $query, ?AccountStatus $accountStatus): Builder
    {
        if (! $accountStatus instanceof AccountStatus) {
            return $query;
        }

        return $this->whereOtherUser($query, static fn (Builder $otherUser): Builder => $otherUser->where('status', $accountStatus));
    }

    /**
     * The rows whose date (accepted, or sent while pending) falls between both dates, both included.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    private function filterBySince(Builder $query, ?CarbonImmutable $from, ?CarbonImmutable $until): Builder
    {
        if ($from instanceof CarbonImmutable) {
            $query->whereRaw('COALESCE(accepted_at, created_at) >= ?', [$from->startOfDay()]);
        }

        if ($until instanceof CarbonImmutable) {
            $query->whereRaw('COALESCE(accepted_at, created_at) <= ?', [$until->endOfDay()]);
        }

        return $query;
    }

    /**
     * The active filter chips of the date range.
     *
     * @return list<string>
     */
    private function dateIndicators(?CarbonImmutable $from, ?CarbonImmutable $until): array
    {
        $indicators = [];

        if ($from instanceof CarbonImmutable) {
            $indicators[] = TranslatedText::of('admin/users.relations.friendships.filters.date_from_indicator', ['date' => $from->isoFormat('LL')]);
        }

        if ($until instanceof CarbonImmutable) {
            $indicators[] = TranslatedText::of('admin/users.relations.friendships.filters.date_until_indicator', ['date' => $until->isoFormat('LL')]);
        }

        return $indicators;
    }

    /**
     * A date chosen in a filter, or null when none was chosen.
     */
    private function filterDate(mixed $date): ?CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date);
        }

        return \is_string($date) && $date !== '' ? CarbonImmutable::parse($date) : null;
    }

    /**
     * Limit the rows to those whose other user matches the constraint: the receiver of the requests
     * the owner sent, or the requester of the ones they received.
     *
     * @param  Builder<Friendship>  $query
     * @param  Closure(Builder<User>): Builder<User>  $constraint
     * @return Builder<Friendship>
     */
    private function whereOtherUser(Builder $query, Closure $constraint): Builder
    {
        $ownerId = $this->getOwner()->id;

        return $query->where(static fn (Builder $builder): Builder => $builder
            ->where(static fn (Builder $sentRequests): Builder => $sentRequests
                ->where('requester_id', $ownerId)
                ->whereHas('receiver', $constraint))
            ->orWhere(static fn (Builder $receivedRequests): Builder => $receivedRequests
                ->where('receiver_id', $ownerId)
                ->whereHas('requester', $constraint)));
    }
}
