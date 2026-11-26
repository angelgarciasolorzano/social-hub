<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Props;

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Queries\MutualFriendsQuery;
use App\Modules\Friendship\Resources\FriendshipListItemResource;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final readonly class FriendshipProps implements ProvidesInertiaProperties
{
    /**
     * The number of recent requests the requests block of the friends page shows.
     */
    public const int RECENT_REQUESTS_LIMIT = 2;

    public function __construct(private Request $request) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'friendRequests' => Inertia::defer(fn (): ?array => $this->friendRequests())
                ->once(as: $this->onceKey()),
        ];
    }

    /**
     * The key the client stores the requests under, one per viewer.
     */
    private function onceKey(): string
    {
        $user = $this->request->user();

        return $user instanceof User ? 'friendRequests.'.$user->id : 'friendRequests.guest';
    }

    /**
     * The viewer's pending received requests, or null for a guest. Runs a constant number of queries.
     *
     * @return array{pendingCount: int, recent: list<mixed>}|null
     */
    private function friendRequests(): ?array
    {
        $user = $this->request->user();

        if (! $user instanceof User) {
            return null;
        }

        $recentFriendRequests = $this->receivedFrom($user)
            ->select('friendships.*')
            ->addSelect(['mutual_friends_count' => new MutualFriendsQuery()->countFor($user, 'friendships.requester_id')])
            ->with('requester.media')
            ->latest()
            ->latest('id')
            ->limit(self::RECENT_REQUESTS_LIMIT)
            ->get();

        return [
            'pendingCount' => $this->receivedFrom($user)->count(),
            'recent' => array_values(FriendshipListItemResource::collection($recentFriendRequests)->resolve($this->request)),
        ];
    }

    /**
     * The pending requests the user received from active accounts.
     *
     * @return Builder<Friendship>
     */
    private function receivedFrom(User $user): Builder
    {
        $builder = Friendship::query();

        $builder->pending();
        $builder->where('receiver_id', $user->id);

        $builder->whereHas('requester', function (Builder $requesterBuilder): void {
            $requesterBuilder->where('status', AccountStatus::Active->value);
        });

        return $builder;
    }
}
