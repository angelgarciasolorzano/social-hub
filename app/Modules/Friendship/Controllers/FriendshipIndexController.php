<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Friendship\Enums\FriendshipListTab;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Requests\FriendshipIndexRequest;
use App\Modules\Friendship\Resources\FriendshipListItemResource;
use App\Support\Database\LikePattern;
use App\Support\Translation\FrontendTranslations;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class FriendshipIndexController extends Controller
{
    public function __invoke(FriendshipIndexRequest $friendshipIndexRequest, FrontendTranslations $frontendTranslations): Response
    {
        $frontendTranslations->use('friendship/friends', 'notification/center');

        $viewer = $friendshipIndexRequest->user();

        abort_unless($viewer instanceof User, 401);

        $friendshipListTab = $friendshipIndexRequest->tab();
        $term = $friendshipIndexRequest->term();

        return Inertia::render('friendship/Friends', [
            'filters' => ['tab' => $friendshipListTab->value, 'q' => $term],
            'tabCounts' => fn (): array => $this->tabCounts($viewer),
            'friendships' => Inertia::scroll(fn (): AnonymousResourceCollection => FriendshipListItemResource::collection(
                $this->friendships($viewer, $friendshipListTab, $term),
            )),
        ]);
    }

    /**
     * The rows of one tab, newest first: friends by the date they became friends, requests by the date
     * they were sent; the id breaks ties so the cursor is stable.
     *
     * @return CursorPaginator<int, Friendship>
     */
    private function friendships(User $user, FriendshipListTab $friendshipListTab, string $term): CursorPaginator
    {
        $dateColumn = $friendshipListTab === FriendshipListTab::Friends ? 'accepted_at' : 'created_at';

        return $this->tabQuery($user, $friendshipListTab, $term)
            ->with(['requester.media', 'receiver.media'])
            ->orderByDesc($dateColumn)
            ->orderByDesc('id')
            ->cursorPaginate(Config::integer('module.friendship.list_page_size'));
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
                    $sentBuilder->where('requester_id', $user->id);
                    $this->withOtherUser($sentBuilder, 'receiver', $term);
                });

                $pairBuilder->orWhere(function (Builder $receivedBuilder) use ($user, $term): void {
                    $receivedBuilder->where('receiver_id', $user->id);
                    $this->withOtherUser($receivedBuilder, 'requester', $term);
                });
            });

            return $builder;
        }

        $viewerIsRequester = $friendshipListTab === FriendshipListTab::Sent;

        $builder->pending();
        $builder->where($viewerIsRequester ? 'requester_id' : 'receiver_id', $user->id);
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
