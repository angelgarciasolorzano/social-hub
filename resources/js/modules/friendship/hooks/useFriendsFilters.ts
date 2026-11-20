import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import FriendshipIndexController from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipIndexController";

import type {
  FriendshipListFilters,
  FriendshipListSort,
  FriendshipListTab,
} from "../types/friendshipList";

interface UseFriendsFiltersOptions {
  delay?: number;
  onTabLoaded?: (tab: FriendshipListTab) => void;
}

interface UseFriendsFiltersReturn {
  changeSort: (sort: FriendshipListSort) => void;
  changeTab: (tab: FriendshipListTab) => void;
  committedFilters: FriendshipListFilters;
  isLoading: boolean;
  loadingTab: FriendshipListTab | null;
  sort: FriendshipListSort;
  term: string;
  updateTerm: (value: string) => void;
}

/**
 * The tab, the sort and the name filter of the friends page, kept in the URL (`?tab=&q=&sort=`) so a
 * list can be shared and reloaded; the defaults (`friends`, no filter, `recent`) stay out of it.
 * Changing the tab or the sort loads the list at once; typing is debounced. Each load replaces the
 * scrolled list instead of merging into it. `committedFilters` describes the rendered list, for the
 * tabs and the empty states, which must not change before its data arrives. A visit cancelled by a
 * newer one leaves the loading state to that newer visit.
 *
 * @param initialFilters      The filters the server rendered the page with.
 * @param options.delay       Debounce delay of the name filter in ms (default 400).
 * @param options.onTabLoaded Called once the list of a newly chosen tab arrives.
 */
export function useFriendsFilters(
  initialFilters: FriendshipListFilters,
  { delay = 400, onTabLoaded }: UseFriendsFiltersOptions = {},
): UseFriendsFiltersReturn {
  const [term, setTerm] = useState<string>(initialFilters.q);

  const [sort, setSort] = useState<FriendshipListSort>(initialFilters.sort);

  const [committedFilters, setCommittedFilters] = useState<FriendshipListFilters>(initialFilters);

  const [loadingTab, setLoadingTab] = useState<FriendshipListTab | null>(null);

  const [isLoading, setIsLoading] = useState<boolean>(false);

  const hasInteractedRef = useRef<boolean>(false);

  const requestedTabRef = useRef<FriendshipListTab>(initialFilters.tab);

  const requestedSortRef = useRef<FriendshipListSort>(initialFilters.sort);

  const termTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const onTabLoadedRef = useRef<UseFriendsFiltersOptions["onTabLoaded"]>(onTabLoaded);

  useEffect(() => {
    onTabLoadedRef.current = onTabLoaded;
  }, [onTabLoaded]);

  const load = useCallback((filters: FriendshipListFilters, isTabChange = false): void => {
    const query: Partial<FriendshipListFilters> = {};

    if (filters.tab !== "friends") query.tab = filters.tab;
    if (filters.q !== "") query.q = filters.q;
    if (filters.sort !== "recent") query.sort = filters.sort;

    router.get(FriendshipIndexController.url(), query, {
      only: ["friendships", "filters"],
      reset: ["friendships"],
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onStart: () => {
        setIsLoading(true);
      },
      onSuccess: () => {
        setCommittedFilters(filters);

        if (isTabChange) onTabLoadedRef.current?.(filters.tab);
      },
      onFinish: (visit) => {
        if (visit.cancelled) return;

        setIsLoading(false);
        setLoadingTab(null);
      },
    });
  }, []);

  const clearTermTimer = (): void => {
    if (termTimerRef.current !== null) clearTimeout(termTimerRef.current);
  };

  useEffect(() => {
    if (!hasInteractedRef.current) return;

    termTimerRef.current = setTimeout(() => {
      load({ q: term.trim(), sort: requestedSortRef.current, tab: requestedTabRef.current });
    }, delay);

    return clearTermTimer;
  }, [term, delay, load]);

  const updateTerm = useCallback((value: string): void => {
    hasInteractedRef.current = true;

    setTerm(value);
  }, []);

  const changeTab = (tab: FriendshipListTab): void => {
    if (tab === requestedTabRef.current) return;

    clearTermTimer();

    requestedTabRef.current = tab;

    setLoadingTab(tab);

    load({ q: term.trim(), sort: requestedSortRef.current, tab }, true);
  };

  const changeSort = (nextSort: FriendshipListSort): void => {
    if (nextSort === requestedSortRef.current) return;

    clearTermTimer();

    requestedSortRef.current = nextSort;

    setSort(nextSort);

    load({ q: term.trim(), sort: nextSort, tab: requestedTabRef.current });
  };

  return {
    changeSort,
    changeTab,
    committedFilters,
    isLoading,
    loadingTab,
    sort,
    term,
    updateTerm,
  };
}
