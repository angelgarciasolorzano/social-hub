<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Resources;

use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin User
 */
class FriendshipSearchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, profilePicture: string, friendshipState: string, friendshipId: int|null, mutualFriendsCount: int}
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'profilePicture' => $this->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
            'friendshipState' => $this->friendshipState($request)->value,
            'friendshipId' => $this->preloadedInteger('friendship_id'),
            'mutualFriendsCount' => (int) $this->preloadedInteger('mutual_friends_count'),
        ];
    }

    /**
     * How this person relates to the user who searched.
     */
    private function friendshipState(Request $request): FriendshipState
    {
        $friendshipStatus = $this->getAttributes()['friendship_status'] ?? null;
        $viewer = $request->user();

        return FriendshipState::forViewer(
            \is_string($friendshipStatus) ? FriendshipStatus::tryFrom($friendshipStatus) : null,
            $this->preloadedInteger('friendship_requester_id'),
            $viewer instanceof User ? $viewer->id : 0,
        );
    }

    /**
     * Read a preloaded numeric column, or null when the subquery found nothing.
     */
    private function preloadedInteger(string $attribute): ?int
    {
        $value = $this->getAttributes()[$attribute] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
