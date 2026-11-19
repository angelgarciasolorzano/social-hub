import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import FriendshipIndexController from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipIndexController";

import type { FriendshipListFilters, FriendshipListTab } from "../types/friendshipList";

interface UseFriendsFiltersReturn {
  changeTab: (tab: FriendshipListTab) => void;
  committedFilters: FriendshipListFilters;
  isLoading: boolean;
  pendingTab: FriendshipListTab | null;
  term: string;
  updateTerm: (value: string) => void;
}

/**
 * The tab and the name filter of the friends page, kept in the URL (`?tab=&q=`) so a list can be shared
 * and reloaded. Changing the tab loads it at once; typing is debounced. Each load replaces the scrolled
 * list instead of merging into it. `committedFilters` describes the rendered list, for the tabs and the
 * empty states, which must not change before its data arrives.
 *
 * @param initialFilters The filters the server rendered the page with.
 * @param delay          Debounce delay of the name filter in ms (default 400).
 */
export function useFriendsFilters(
  initialFilters: FriendshipListFilters,
  delay = 400,
): UseFriendsFiltersReturn {
  const [term, setTerm] = useState<string>(initialFilters.q);

  const [committedFilters, setCommittedFilters] = useState<FriendshipListFilters>(initialFilters);

  const [pendingTab, setPendingTab] = useState<FriendshipListTab | null>(null);

  const [isLoading, setIsLoading] = useState<boolean>(false);

  const hasInteractedRef = useRef<boolean>(false);

  const requestedTabRef = useRef<FriendshipListTab>(initialFilters.tab);

  const termTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback((filters: FriendshipListFilters): void => {
    const query: Partial<FriendshipListFilters> = {};

    if (filters.tab !== "friends") query.tab = filters.tab;
    if (filters.q !== "") query.q = filters.q;

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
      },
      onFinish: (visit) => {
        setIsLoading(false);

        if (!visit.cancelled) setPendingTab(null);
      },
    });
  }, []);

  const clearTermTimer = (): void => {
    if (termTimerRef.current !== null) clearTimeout(termTimerRef.current);
  };

  useEffect(() => {
    if (!hasInteractedRef.current) return;

    termTimerRef.current = setTimeout(() => {
      load({ q: term.trim(), tab: requestedTabRef.current });
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

    setPendingTab(tab);

    load({ q: term.trim(), tab });
  };

  return { changeTab, committedFilters, isLoading, pendingTab, term, updateTerm };
}
