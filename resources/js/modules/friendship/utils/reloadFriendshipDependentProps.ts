import { router } from "@inertiajs/react";

import type { FriendshipState } from "../types/friendshipSearch";

interface FriendshipDependentProps {
  only: string[];
  onlyWhenFriends: boolean;
  reset: string[];
}

const FRIENDSHIP_DEPENDENT_PROPS_OF_PAGE: Record<string, FriendshipDependentProps> = {
  "home/Home": { only: ["posts"], onlyWhenFriends: true, reset: ["posts"] },
  "friendship/pages/Friends": {
    only: ["tabCounts", "friendships"],
    onlyWhenFriends: false,
    reset: ["friendships"],
  },
  "profile/Profile": { only: ["profileFriendship"], onlyWhenFriends: false, reset: [] },
};

/**
 * Reloads the friend requests of the sidebars after a friendship changed, together with the props
 * of the open page that depend on it, in a single request. The feed only changes when both users
 * became friends.
 *
 * @param component - Inertia component of the open page.
 * @param friendshipState - State of the friendship after the change.
 */
export function reloadFriendshipDependentProps(
  component: string,
  friendshipState: FriendshipState,
): void {
  const pageProps = FRIENDSHIP_DEPENDENT_PROPS_OF_PAGE[component];

  const reloadsPageProps =
    pageProps !== undefined && (!pageProps.onlyWhenFriends || friendshipState === "friends");

  router.reload({
    only: reloadsPageProps ? ["friendRequests", ...pageProps.only] : ["friendRequests"],
    reset: reloadsPageProps ? pageProps.reset : [],
  });
}
