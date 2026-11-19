import type { FriendshipState } from "./friendshipSearch";

export type FriendshipListTab = "friends" | "received" | "sent";

export interface FriendshipListUser {
  id: number;
  name: string;
  profilePicture: string;
}

export interface FriendshipListItem {
  friendshipId: number;
  friendshipState: FriendshipState;
  since: null | string;
  user: FriendshipListUser;
}

export interface FriendshipListItemCollection {
  data: FriendshipListItem[];
}

export interface FriendshipListFilters {
  q: string;
  tab: FriendshipListTab;
}

export type FriendshipTabCounts = Record<FriendshipListTab, number>;
