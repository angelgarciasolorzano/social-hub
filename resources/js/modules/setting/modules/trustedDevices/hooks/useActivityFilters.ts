import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import {
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceActivityFilters";
import type { TrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

export interface ActivityFilterState {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter[] | null;
  search: string;
}

interface UseActivityFiltersReturn {
  committedFilters: ActivityFilterState;
  filters: ActivityFilterState;
  goToPage: (page: number) => void;
  resetFilters: () => void;
  updateFilter: <K extends keyof ActivityFilterState>(
    key: K,
    value: ActivityFilterState[K],
  ) => void;
}

/**
 * Filter state + reload behaviour for the activity dialog.
 *
 * `updateFilter` mutates state; the effect below triggers the reload via
 * `router.reload` (no URL mutation, suitable for dialogs) with `search`
 * debounced and the other fields firing immediately.
 *
 * @param initialFilters  Sanitized filters emitted by the backend.
 * @param delay           Debounce delay in ms for the `search` field (default 500).
 */
export function useActivityFilters(
  initialFilters: TrustedDeviceActivityFilters,
  delay = 500,
): UseActivityFiltersReturn {
  const [filters, setFilters] = useState<ActivityFilterState>({
    action: initialFilters.action ?? [],
    search: initialFilters.search,
    sinceDays: initialFilters.sinceDays ?? [],
  });

  const [committedFilters, setCommittedFilters] = useState<ActivityFilterState>(filters);

  const isFirstSearchRenderRef = useRef<boolean>(true);
  const isFirstNonSearchRenderRef = useRef<boolean>(true);
  const filtersRef = useRef<ActivityFilterState>(filters);

  useEffect(() => {
    filtersRef.current = filters;
  }, [filters]);

  const triggerReload = useCallback((next: ActivityFilterState, page?: number): void => {
    const action =
      next.action === null || next.action.length === 0 ? undefined : next.action.join(",");

    const search = next.search === "" ? undefined : next.search;

    const sinceDays =
      next.sinceDays === null || next.sinceDays.length === 0 ? undefined : next.sinceDays.join(",");

    router.reload({
      data: {
        action: action,
        page,
        search: search,
        since_days: sinceDays,
      },
      only: ["activityLog"],
      preserveUrl: true,
      replace: true,
      onFinish: () => {
        setCommittedFilters(next);
      },
    });
  }, []);

  useEffect(() => {
    if (isFirstSearchRenderRef.current) {
      isFirstSearchRenderRef.current = false;

      return;
    }

    const timer = setTimeout(() => {
      triggerReload(filtersRef.current);
    }, delay);

    return () => {
      clearTimeout(timer);
    };
  }, [filters.search, delay, triggerReload]);

  useEffect(() => {
    if (isFirstNonSearchRenderRef.current) {
      isFirstNonSearchRenderRef.current = false;

      return;
    }

    triggerReload(filtersRef.current);
  }, [filters.action, filters.sinceDays, triggerReload]);

  const updateFilter = useCallback(
    <K extends keyof ActivityFilterState>(key: K, value: ActivityFilterState[K]): void => {
      setFilters((prev) => ({ ...prev, [key]: value }));
    },
    [],
  );

  const goToPage = useCallback(
    (page: number): void => {
      triggerReload(filtersRef.current, page);
    },
    [triggerReload],
  );

  const resetFilters = useCallback(() => {
    setFilters({
      action: [],
      search: "",
      sinceDays: [],
    });
  }, []);

  return { committedFilters, filters, goToPage, resetFilters, updateFilter };
}
