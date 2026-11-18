<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Services;

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Config;

/**
 * Finds people by name for the friend search. Each result carries, in the same query, the row
 * the viewer shares with that person and how many friends they have in common, so serializing
 * a page costs the same number of queries whatever its size.
 */
final readonly class FriendshipSearchService
{
    /**
     * Search active accounts whose name starts with the term, except the viewer, ordered by name.
     * `%`, `_` and `\` in the term match themselves instead of acting as wildcards.
     *
     * @return CursorPaginator<int, User>
     */
    public function search(User $user, string $term): CursorPaginator
    {
        return User::query()
            ->select(['users.id', 'users.name'])
            ->whereKeyNot($user->id)
            ->where('status', AccountStatus::Active->value)
            ->where('name', 'like', $this->escapedForLike($term).'%')
            ->addSelect([
                'friendship_id' => $this->pairWith($user)->select('friendships.id'),
                'friendship_status' => $this->pairWith($user)->select('friendships.status'),
                'friendship_requester_id' => $this->pairWith($user)->select('friendships.requester_id'),
                'mutual_friends_as_requester_count' => $this->mutualFriendsCount($user, 'requester_id', 'receiver_id'),
                'mutual_friends_as_receiver_count' => $this->mutualFriendsCount($user, 'receiver_id', 'requester_id'),
            ])
            ->with('media')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate(Config::integer('module.friendship.search_page_size'));
    }

    /**
     * Escape the characters with a meaning in `LIKE`, backslash first, MySQL's default escape character.
     */
    private function escapedForLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * The row between the viewer and the user of the outer query, found through the unique pair index.
     *
     * @return Builder<Friendship>
     */
    private function pairWith(User $user): Builder
    {
        $builder = Friendship::query();
        $builder->whereRaw('friendships.user_low_id = LEAST(?, users.id)', [$user->id])
            ->whereRaw('friendships.user_high_id = GREATEST(?, users.id)', [$user->id])
            ->limit(1);

        return $builder;
    }

    /**
     * Count the outer user's accepted friendships on one side of the pair whose other side is a friend of the viewer.
     *
     * @param  'requester_id'|'receiver_id'  $userColumn
     * @param  'requester_id'|'receiver_id'  $friendColumn
     */
    private function mutualFriendsCount(User $user, string $userColumn, string $friendColumn): QueryBuilder
    {
        return Friendship::query()
            ->toBase()
            ->selectRaw('count(*)')
            ->where('friendships.status', FriendshipStatus::Accepted->value)
            ->whereColumn("friendships.{$userColumn}", 'users.id')
            ->whereIn("friendships.{$friendColumn}", $this->friendIdsOf($user));
    }

    /**
     * The ids of the viewer's friends, read through both `(<side>_id, status)` indexes.
     */
    private function friendIdsOf(User $user): QueryBuilder
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
}
