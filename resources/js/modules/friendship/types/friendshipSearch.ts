export type FriendshipState = "friends" | "none" | "request_received" | "request_sent";

export interface FriendSearchResult {
  friendshipId: null | number;
  friendshipState: FriendshipState;
  id: number;
  mutualFriendsCount: number;
  name: string;
  profilePicture: string;
}

export interface FriendSearchResultCollection {
  data: FriendSearchResult[];
}

export interface FriendSearchFilters {
  q: string;
}

export interface FriendshipActionResponse {
  message: string;
  result: string;
  state: FriendshipState;
}
