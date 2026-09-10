import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import {
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceActivityFilters";
import type { TrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

export interface ActivityFilterState {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter;
  search: string;
}

interface UseActivityFiltersReturn {
  filters: ActivityFilterState;
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
    sinceDays: initialFilters.sinceDays,
  });

  const isFirstSearchRenderRef = useRef<boolean>(true);
  const isFirstNonSearchRenderRef = useRef<boolean>(true);
  const filtersRef = useRef<ActivityFilterState>(filters);

  useEffect(() => {
    filtersRef.current = filters;
  }, [filters]);

  const triggerReload = useCallback((next: ActivityFilterState): void => {
    router.reload({
      data: {
        action:
          next.action === null || next.action.length === 0 ? undefined : next.action.join(","),
        search: next.search === "" ? undefined : next.search,
        since_days: next.sinceDays,
      },
      only: ["activityLog"],
      preserveUrl: true,
      replace: true,
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

  return { filters, updateFilter };
}
