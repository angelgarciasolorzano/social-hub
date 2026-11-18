export type FriendshipState = "friends" | "none" | "request_received" | "request_sent";

export interface FriendSearchResult {
  friendshipId: null | number;
  friendshipState: FriendshipState;
  id: number;
  mutualFriendsCount: number;
  name: string;
  profilePicture: string;
}

export interface FriendSearchPage {
  data: FriendSearchResult[];
  next_cursor: null | string;
  next_page_url: null | string;
  path: string;
  per_page: number;
  prev_cursor: null | string;
  prev_page_url: null | string;
}

export interface FriendshipActionResponse {
  message: string;
  result: string;
  state: FriendshipState;
}
