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
 * `updateFilter` mutates state; the effects below trigger the reload via
 * `router.reload` (no URL mutation, suitable for dialogs) with `search`
 * debounced and the other fields firing immediately. Both effects are
 * gated on `hasInteractedRef`, set only inside `updateFilter`/`resetFilters`
 * — i.e. an actual user action — rather than an "is this the first render"
 * ref flipped inside the effect itself. The latter breaks under React
 * StrictMode's dev-only double-invoke-on-mount behavior: the first
 * simulated pass flips the flag, so the second pass sees it already
 * false and fires a spurious reload before the user has touched anything.
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

  const hasInteractedRef = useRef<boolean>(false);
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
      only: ["activityDialog"],
      preserveUrl: true,
      replace: true,
      onFinish: () => {
        setCommittedFilters(next);
      },
    });
  }, []);

  useEffect(() => {
    if (!hasInteractedRef.current) return;

    const timer = setTimeout(() => {
      triggerReload(filtersRef.current);
    }, delay);

    return () => {
      clearTimeout(timer);
    };
  }, [filters.search, delay, triggerReload]);

  useEffect(() => {
    if (!hasInteractedRef.current) return;

    triggerReload(filtersRef.current);
  }, [filters.action, filters.sinceDays, triggerReload]);

  const updateFilter = useCallback(
    <K extends keyof ActivityFilterState>(key: K, value: ActivityFilterState[K]): void => {
      hasInteractedRef.current = true;

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
    hasInteractedRef.current = true;

    setFilters({
      action: [],
      search: "",
      sinceDays: [],
    });
  }, []);

  return { committedFilters, filters, goToPage, resetFilters, updateFilter };
}
