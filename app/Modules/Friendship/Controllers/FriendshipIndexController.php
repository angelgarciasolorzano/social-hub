<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Enums\FriendshipListSort;
use App\Modules\Friendship\Enums\FriendshipListTab;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Queries\MutualFriendsQuery;
use App\Modules\Friendship\Requests\FriendshipIndexRequest;
use App\Modules\Friendship\Resources\FriendshipListItemResource;
use App\Support\Database\LikePattern;
use App\Support\Translation\FrontendTranslations;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class FriendshipIndexController extends Controller
{
    /**
     * The alias of the other person of each row, joined so the list can sort by their name and count
     * the friends they share with the viewer.
     */
    private const string OTHER_USER_TABLE = 'other_users';

    public function __construct(private readonly MutualFriendsQuery $mutualFriendsQuery) {}

    public function __invoke(FriendshipIndexRequest $friendshipIndexRequest, FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('friendship/friends', 'notification/center');

        $viewer = $friendshipIndexRequest->user();

        abort_unless($viewer instanceof User, 401);

        $friendshipListTab = $friendshipIndexRequest->tab();
        $term = $friendshipIndexRequest->term();
        $friendshipListSort = $friendshipIndexRequest->sort();

        return Inertia::render('friendship/pages/Friends', [
            'filters' => ['tab' => $friendshipListTab->value, 'q' => $term, 'sort' => $friendshipListSort->value],
            'tabCounts' => fn (): array => $this->tabCounts($viewer),
            'friendships' => Inertia::scroll(fn (): AnonymousResourceCollection => FriendshipListItemResource::collection(
                $this->friendships($viewer, $friendshipListTab, $term, $friendshipListSort),
            )),
        ]);
    }

    /**
     * The rows of one tab with the friends each other person shares with the viewer. The most recent
     * first sorts friends by the date they became friends and requests by the date they were sent; the
     * name sort uses the other person's name. The friendship id breaks ties so the cursor is stable.
     *
     * @return CursorPaginator<int, Friendship>
     */
    private function friendships(User $user, FriendshipListTab $friendshipListTab, string $term, FriendshipListSort $friendshipListSort): CursorPaginator
    {
        $builder = $this->tabQuery($user, $friendshipListTab, $term)
            ->select('friendships.*')
            ->join('users as '.self::OTHER_USER_TABLE, function (JoinClause $joinClause) use ($user): void {
                $joinClause->whereRaw(
                    'other_users.id = CASE WHEN friendships.requester_id = ? THEN friendships.receiver_id ELSE friendships.requester_id END',
                    [$user->id],
                );
            })
            ->addSelect(['mutual_friends_count' => $this->mutualFriendsQuery->countFor($user, self::OTHER_USER_TABLE.'.id')])
            ->with(['requester.media', 'receiver.media']);

        if ($friendshipListSort === FriendshipListSort::Name) {
            $builder->addSelect(self::OTHER_USER_TABLE.'.name as other_user_name')
                ->orderBy('other_user_name')
                ->orderBy('friendships.id');
        } else {
            $builder->orderByDesc($friendshipListTab === FriendshipListTab::Friends ? 'friendships.accepted_at' : 'friendships.created_at')
                ->orderByDesc('friendships.id');
        }

        return $builder->cursorPaginate(Config::integer('module.friendship.list_page_size'));
    }

    /**
     * The size of every tab without the name filter, so typing a name does not change the counters.
     * Runs one query per tab, whatever the number of friendships.
     *
     * @return array{friends: int, received: int, sent: int}
     */
    private function tabCounts(User $user): array
    {
        return [
            FriendshipListTab::Friends->value => $this->tabQuery($user, FriendshipListTab::Friends)->count(),
            FriendshipListTab::Received->value => $this->tabQuery($user, FriendshipListTab::Received)->count(),
            FriendshipListTab::Sent->value => $this->tabQuery($user, FriendshipListTab::Sent)->count(),
        ];
    }

    /**
     * The viewer's rows of a tab whose other person is active and, with a term, whose name starts with it.
     *
     * @return Builder<Friendship>
     */
    private function tabQuery(User $user, FriendshipListTab $friendshipListTab, string $term = ''): Builder
    {
        $builder = Friendship::query();

        if ($friendshipListTab === FriendshipListTab::Friends) {
            $builder->accepted();

            $builder->where(function (Builder $pairBuilder) use ($user, $term): void {
                $pairBuilder->where(function (Builder $sentBuilder) use ($user, $term): void {
                    $sentBuilder->where($sentBuilder->qualifyColumn('requester_id'), $user->id);
                    $this->withOtherUser($sentBuilder, 'receiver', $term);
                });

                $pairBuilder->orWhere(function (Builder $receivedBuilder) use ($user, $term): void {
                    $receivedBuilder->where($receivedBuilder->qualifyColumn('receiver_id'), $user->id);
                    $this->withOtherUser($receivedBuilder, 'requester', $term);
                });
            });

            return $builder;
        }

        $viewerIsRequester = $friendshipListTab === FriendshipListTab::Sent;

        $builder->pending();
        $builder->where($builder->qualifyColumn($viewerIsRequester ? 'requester_id' : 'receiver_id'), $user->id);
        $this->withOtherUser($builder, $viewerIsRequester ? 'receiver' : 'requester', $term);

        return $builder;
    }

    /**
     * Keep the rows whose other person, reached through the given relation, is active and matches the term.
     *
     * @param  Builder<Friendship>  $builder
     * @param  'requester'|'receiver'  $relation
     */
    private function withOtherUser(Builder $builder, string $relation, string $term): void
    {
        $builder->whereHas($relation, function (Builder $userBuilder) use ($term): void {
            $userBuilder->where('status', AccountStatus::Active->value);

            if ($term !== '') {
                $userBuilder->where('name', 'like', LikePattern::startsWith($term));
            }
        });
    }
}
