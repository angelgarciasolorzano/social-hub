import { useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import type { FriendshipListFilters, FriendshipListItem } from "../types/friendshipList";

interface HiddenFriendships {
  friendshipIds: number[];
  listKey: string;
}

interface UseHiddenFriendshipsReturn {
  hideFriendship: (friendshipListItem: FriendshipListItem) => void;
  visibleFriendships: FriendshipListItem[];
}

/**
 * The rows of the rendered list without the ones whose friendship left the tab (accepted, declined,
 * cancelled or removed from a card). The hidden ids belong to one list (tab, filter and sort), so a new
 * list shows every row again. When every loaded row is hidden, the list is reloaded once so the next
 * page, if any, fills it.
 *
 * @param friendshipListItems The loaded rows of the list.
 * @param committedFilters    The filters of the rendered list.
 */
export function useHiddenFriendships(
  friendshipListItems: FriendshipListItem[],
  committedFilters: FriendshipListFilters,
): UseHiddenFriendshipsReturn {
  const listKey = `${committedFilters.tab}:${committedFilters.q}:${committedFilters.sort}`;

  const [hiddenFriendships, setHiddenFriendships] = useState<HiddenFriendships>({
    friendshipIds: [],
    listKey,
  });

  const reloadedEmptyListRef = useRef<FriendshipListItem[] | null>(null);

  const hiddenFriendshipIds =
    hiddenFriendships.listKey === listKey ? hiddenFriendships.friendshipIds : [];

  const visibleFriendships = friendshipListItems.filter(
    (friendshipListItem) => !hiddenFriendshipIds.includes(friendshipListItem.friendshipId),
  );

  const hidesEveryLoadedRow = friendshipListItems.length > 0 && visibleFriendships.length === 0;

  useEffect(() => {
    if (!hidesEveryLoadedRow || reloadedEmptyListRef.current === friendshipListItems) return;

    reloadedEmptyListRef.current = friendshipListItems;

    router.reload({ only: ["friendships"], reset: ["friendships"] });
  }, [friendshipListItems, hidesEveryLoadedRow]);

  const hideFriendship = (friendshipListItem: FriendshipListItem): void => {
    setHiddenFriendships((currentHiddenFriendships) => ({
      friendshipIds: [
        ...(currentHiddenFriendships.listKey === listKey
          ? currentHiddenFriendships.friendshipIds
          : []),
        friendshipListItem.friendshipId,
      ],
      listKey,
    }));
  };

  return { hideFriendship, visibleFriendships };
}
