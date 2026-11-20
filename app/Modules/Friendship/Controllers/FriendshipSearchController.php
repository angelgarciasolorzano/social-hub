<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Queries\MutualFriendsQuery;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\Modules\Friendship\Resources\FriendshipSearchResultResource;
use App\Support\Database\LikePattern;
use App\Support\Translation\FrontendTranslations;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class FriendshipSearchController extends Controller
{
    public function __construct(private readonly MutualFriendsQuery $mutualFriendsQuery) {}

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
            ->where('name', 'like', LikePattern::startsWith($term))
            ->addSelect([
                'friendship_id' => $this->pairWith($user)->select('friendships.id'),
                'friendship_status' => $this->pairWith($user)->select('friendships.status'),
                'friendship_requester_id' => $this->pairWith($user)->select('friendships.requester_id'),
                'mutual_friends_count' => $this->mutualFriendsQuery->countFor($user, 'users.id'),
            ])
            ->with('media')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage);
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
}
