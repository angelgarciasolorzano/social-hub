import { useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import type {
  FriendshipListFilters,
  FriendshipListItem,
  FriendshipListItemCollection,
  FriendshipListSort,
  FriendshipListTab,
} from "../types/friendshipList";
import type { FriendshipState } from "../types/friendshipSearch";
import { useFriendsFilters } from "./useFriendsFilters";
import { useFriendsShortcuts } from "./useFriendsShortcuts";
import { useHiddenFriendships } from "./useHiddenFriendships";

const FRIENDSHIP_STATE_OF_TAB: Record<FriendshipListTab, FriendshipState> = {
  friends: "friends",
  received: "request_received",
  sent: "request_sent",
};

interface UseFriendsPageOptions {
  filters: FriendshipListFilters;
  friendships: FriendshipListItemCollection;
}

interface UseFriendsPageReturn {
  changeFriendship: (
    friendshipListItem: FriendshipListItem,
    friendshipState: FriendshipState,
  ) => void;
  changeSort: (sort: FriendshipListSort) => void;
  changeTab: (tab: FriendshipListTab) => void;
  clearTerm: () => void;
  committedFilters: FriendshipListFilters;
  isLoading: boolean;
  isReloading: boolean;
  isShortcutsDialogOpen: boolean;
  reloadFriendships: () => void;
  selectedTab: FriendshipListTab;
  setFilterInput: (filterInput: HTMLInputElement | null) => void;
  setIsShortcutsDialogOpen: (isOpen: boolean) => void;
  sort: FriendshipListSort;
  term: string;
  updateTerm: (value: string) => void;
  visibleFriendships: FriendshipListItem[];
}

/**
 * The state of the friends page: the filters, the keyboard shortcuts, the manual reload and the rows
 * hidden after a friendship leaves the tab. The name filter gets the focus on load and after a tab
 * loads, except when a shortcut changed the tab (the keyboard stays on the page).
 */
export function useFriendsPage({
  filters,
  friendships,
}: UseFriendsPageOptions): UseFriendsPageReturn {
  const [filterInput, setFilterInput] = useState<HTMLInputElement | null>(null);

  const [isShortcutsDialogOpen, setIsShortcutsDialogOpen] = useState<boolean>(false);

  const [isReloading, setIsReloading] = useState<boolean>(false);

  const tabChangedByShortcutRef = useRef<boolean>(false);

  const focusFilter = (): void => {
    filterInput?.focus();
  };

  const focusFilterAfterTabLoad = (): void => {
    if (tabChangedByShortcutRef.current) {
      tabChangedByShortcutRef.current = false;

      return;
    }

    focusFilter();
  };

  const { changeSort, changeTab, committedFilters, isLoading, loadingTab, sort, term, updateTerm } =
    useFriendsFilters(filters, { onTabLoaded: focusFilterAfterTabLoad });

  const { hideFriendship, visibleFriendships } = useHiddenFriendships(
    friendships.data,
    committedFilters,
  );

  const selectedTab = loadingTab ?? committedFilters.tab;

  useEffect(() => {
    filterInput?.focus();
  }, [filterInput]);

  useFriendsShortcuts({
    enabled: !isShortcutsDialogOpen,
    filterInput,
    onChangeTab: (tab) => {
      tabChangedByShortcutRef.current = tab !== selectedTab;

      changeTab(tab);
    },
    onFocusFilter: focusFilter,
    onOpenHelp: () => {
      setIsShortcutsDialogOpen(true);
    },
    onToggleSort: () => {
      changeSort(sort === "recent" ? "name" : "recent");
    },
  });

  const clearTerm = (): void => {
    updateTerm("");

    focusFilter();
  };

  const changeFriendship = (
    friendshipListItem: FriendshipListItem,
    friendshipState: FriendshipState,
  ): void => {
    if (friendshipState !== FRIENDSHIP_STATE_OF_TAB[committedFilters.tab]) {
      hideFriendship(friendshipListItem);
    }

    router.reload({ async: true, only: ["tabCounts", "friendRequests"] });
  };

  const reloadFriendships = (): void => {
    router.reload({
      only: ["friendships", "tabCounts", "friendRequests"],
      onFinish: () => {
        setIsReloading(false);
      },
      onStart: () => {
        setIsReloading(true);
      },
      reset: ["friendships"],
    });
  };

  return {
    changeFriendship,
    changeSort,
    changeTab,
    clearTerm,
    committedFilters,
    isLoading,
    isReloading,
    isShortcutsDialogOpen,
    reloadFriendships,
    selectedTab,
    setFilterInput,
    setIsShortcutsDialogOpen,
    sort,
    term,
    updateTerm,
    visibleFriendships,
  };
}
