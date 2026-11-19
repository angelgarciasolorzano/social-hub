<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\Modules\Friendship\Resources\FriendshipSearchResultResource;
use App\Support\Translation\FrontendTranslations;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the people search page. It is a page of its own, apart from the JSON actions of
 * `FriendshipController`, like `TrustedDeviceIndexController`; the friends page (SOC-84) is the index.
 * Each result carries, in the same query, the row the viewer shares with that person and how many
 * friends they have in common, so a page costs the same number of queries whatever its size.
 */
final class FriendshipSearchController extends Controller
{
    /**
     * Render the page: the term lives in the URL and the results scroll by cursor.
     */
    public function __invoke(FriendshipSearchRequest $friendshipSearchRequest, FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('friendship/search', 'notification/center');

        $viewer = $friendshipSearchRequest->user();

        abort_unless($viewer instanceof User, 401);

        $term = $friendshipSearchRequest->string('q')->trim()->toString();

        return Inertia::render('friendship/Search', [
            'filters' => ['q' => $term],
            'searchTermMinLength' => FriendshipSearchRequest::TERM_MIN_LENGTH,
            'searchResults' => Inertia::scroll(fn (): AnonymousResourceCollection => FriendshipSearchResultResource::collection(
                $this->searchResults($viewer, $term),
            )),
        ]);
    }

    /**
     * Search active accounts whose name starts with the term, except the viewer, ordered by name.
     * `%`, `_` and `\` match themselves; a term shorter than the minimum lists nobody without a query.
     *
     * @return CursorPaginator<int, User>
     */
    private function searchResults(User $user, string $term): CursorPaginator
    {
        $perPage = Config::integer('module.friendship.search_page_size');

        if (mb_strlen($term) < FriendshipSearchRequest::TERM_MIN_LENGTH) {
            return new CursorPaginator([], $perPage, null, ['path' => Paginator::resolveCurrentPath()]);
        }

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
            ->cursorPaginate($perPage);
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
