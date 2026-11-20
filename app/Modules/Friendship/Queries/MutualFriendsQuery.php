<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Queries;

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class MutualFriendsQuery
{
    /**
     * The alias of the counted rows, so a `friendships.*` column always refers to the outer query.
     */
    private const string COUNTED_TABLE = 'mutual_friendships';

    /**
     * The ids of the viewer's friends, read through both `(<side>_id, status)` indexes.
     */
    public function friendIdsOf(User $user): QueryBuilder
    {
        return Friendship::query()
            ->toBase()
            ->select('receiver_id')
            ->where('status', FriendshipStatus::Accepted->value)
            ->where('requester_id', $user->id)
            ->unionAll(Friendship::query()
                ->toBase()
                ->select('requester_id')
                ->where('status', FriendshipStatus::Accepted->value)
                ->where('receiver_id', $user->id));
    }

    /**
     * A scalar subquery with the number of accepted friends of the outer user who are also friends of
     * the viewer. It adds one count per side of the pair, so each side reads its own index.
     */
    public function countFor(User $user, string $otherUserIdColumn): QueryBuilder
    {
        return DB::query()
            ->fromSub(
                $this->countOnSide($user, $otherUserIdColumn, 'requester_id', 'receiver_id')
                    ->unionAll($this->countOnSide($user, $otherUserIdColumn, 'receiver_id', 'requester_id')),
                'mutual_friends_per_side',
            )
            ->selectRaw('sum(side_count)');
    }

    /**
     * Count the outer user's accepted friendships on one side of the pair whose other side is a friend of the viewer.
     *
     * @param  'requester_id'|'receiver_id'  $userColumn
     * @param  'requester_id'|'receiver_id'  $friendColumn
     */
    private function countOnSide(User $user, string $otherUserIdColumn, string $userColumn, string $friendColumn): QueryBuilder
    {
        return Friendship::query()
            ->toBase()
            ->from('friendships', self::COUNTED_TABLE)
            ->selectRaw('count(*) as side_count')
            ->where(self::COUNTED_TABLE.'.status', FriendshipStatus::Accepted->value)
            ->whereColumn(self::COUNTED_TABLE.'.'.$userColumn, $otherUserIdColumn)
            ->whereIn(self::COUNTED_TABLE.'.'.$friendColumn, $this->friendIdsOf($user));
    }
}
