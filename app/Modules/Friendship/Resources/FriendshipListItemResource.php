<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Resources;

use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Models\Friendship;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Override;

/**
 * @mixin Friendship
 */
class FriendshipListItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{friendshipId: int, friendshipState: string, since: string|null, mutualFriendsCount: int, user: array{id: int, name: string, profilePicture: string}}
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $viewerId = $viewer instanceof User ? $viewer->id : 0;
        $otherUser = $this->otherUserFor($viewerId);

        return [
            'friendshipId' => $this->id,
            'friendshipState' => FriendshipState::forViewer($this->status, $this->requester_id, $viewerId)->value,
            'since' => ($this->isAccepted() ? $this->accepted_at : $this->created_at)?->toIso8601String(),
            'mutualFriendsCount' => $this->mutualFriendsCount(),
            'user' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'profilePicture' => $otherUser->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
            ],
        ];
    }

    /**
     * The friends the other person shares with the viewer, preloaded by the listing's query so a row
     * never runs a query of its own.
     *
     * @throws LogicException
     */
    private function mutualFriendsCount(): int
    {
        $mutualFriendsCount = $this->getAttributes()['mutual_friends_count'] ?? null;

        throw_unless(is_numeric($mutualFriendsCount), LogicException::class, "Select mutual_friends_count before serializing friendship {$this->id}.");

        return (int) $mutualFriendsCount;
    }

    /**
     * The person of the pair who is not the viewer: the receiver for the requester, the requester for
     * anyone else. Reads only the loaded relation, so a missing eager load fails instead of querying.
     *
     * @throws LogicException
     */
    private function otherUserFor(int $viewerId): User
    {
        $relation = $this->requester_id === $viewerId ? 'receiver' : 'requester';

        throw_unless($this->relationLoaded($relation), LogicException::class, "Load the {$relation} relation before serializing a friendship list item.");

        $otherUser = $this->getRelation($relation);

        throw_unless($otherUser instanceof User, LogicException::class, "The {$relation} of friendship {$this->id} is missing.");

        return $otherUser;
    }
}
