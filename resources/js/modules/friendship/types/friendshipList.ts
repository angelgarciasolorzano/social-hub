import type { FriendshipState } from "./friendshipSearch";

export type FriendshipListSort = "name" | "recent";

export type FriendshipListTab = "friends" | "received" | "sent";

export interface FriendshipListUser {
  id: number;
  name: string;
  profilePicture: string;
}

export interface FriendshipListItem {
  friendshipId: number;
  friendshipState: FriendshipState;
  mutualFriendsCount: number;
  since: null | string;
  user: FriendshipListUser;
}

export interface FriendshipListItemCollection {
  data: FriendshipListItem[];
}

export interface FriendshipListFilters {
  q: string;
  sort: FriendshipListSort;
  tab: FriendshipListTab;
}

export type FriendshipTabCounts = Record<FriendshipListTab, number>;
