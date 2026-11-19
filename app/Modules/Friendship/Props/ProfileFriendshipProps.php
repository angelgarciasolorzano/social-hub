<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Props;

use App\Modules\Friendship\Enums\FriendshipState;
use App\User\Enums\AccountStatus;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final readonly class ProfileFriendshipProps implements ProvidesInertiaProperties
{
    private function __construct(private User $profileUser, private User $viewer) {}

    /**
     * The friendship data of the given profile, as the viewer sees it.
     */
    public static function for(User $profileUser, User $viewer): self
    {
        return new self($profileUser, $viewer);
    }

    /**
     * @return array{profileFriendship: array{friendsCount: int, isOwnProfile: bool, friendshipState: string|null, friendshipId: int|null, mutualFriendsCount: int}}
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        $isOwnProfile = $this->profileUser->is($this->viewer);
        $friendship = $isOwnProfile ? null : $this->viewer->friendshipWith($this->profileUser);

        return [
            'profileFriendship' => [
                'friendsCount' => $this->activeFriendsOf($this->profileUser)->count(),
                'isOwnProfile' => $isOwnProfile,
                'friendshipState' => $isOwnProfile
                    ? null
                    : FriendshipState::forViewer($friendship?->status, $friendship?->requester_id, $this->viewer->id)->value,
                'friendshipId' => $friendship?->id,
                'mutualFriendsCount' => $isOwnProfile ? 0 : $this->mutualFriendsCount(),
            ],
        ];
    }

    /**
     * The active friends both users share, counted in a single query: the profile owner's friends whose
     * id is also among the viewer's friend ids.
     */
    private function mutualFriendsCount(): int
    {
        $builder = $this->viewer->friends()->select('users.id');

        return $this->activeFriendsOf($this->profileUser)
            ->whereIn('users.id', $builder)
            ->count();
    }

    /**
     * The user's accepted friends whose account is active.
     *
     * @return Builder<User>
     */
    private function activeFriendsOf(User $user): Builder
    {
        $builder = $user->friends();
        $builder->where('status', AccountStatus::Active->value);

        return $builder;
    }
}
