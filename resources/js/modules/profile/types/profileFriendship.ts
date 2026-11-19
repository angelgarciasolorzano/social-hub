import type { FriendshipState } from "@/modules/friendship/types/friendshipSearch";

export interface ProfileFriendship {
  friendsCount: number;
  friendshipId: null | number;
  friendshipState: FriendshipState | null;
  isOwnProfile: boolean;
  mutualFriendsCount: number;
}
