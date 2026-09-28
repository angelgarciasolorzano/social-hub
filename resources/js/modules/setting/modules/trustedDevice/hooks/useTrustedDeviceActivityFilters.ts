import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import {
  defaultTrustedDeviceActivityFilters,
  type TrustedDeviceActivityActionFilter,
  type TrustedDeviceActivitySinceDaysFilter,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type { TrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";

export interface TrustedDeviceActivityFilterState {
  action: TrustedDeviceActivityActionFilter[] | null;
  sinceDays: TrustedDeviceActivitySinceDaysFilter[] | null;
  search: string;
}

export interface TrustedDeviceActivityFiltersController {
  committedFilters: TrustedDeviceActivityFilterState;
  filters: TrustedDeviceActivityFilterState;
  goToPage: (page: number) => void;
  reload: (onFinish?: () => void) => void;
  resetFilters: () => void;
  updateFilter: <K extends keyof TrustedDeviceActivityFilterState>(
    key: K,
    value: TrustedDeviceActivityFilterState[K],
  ) => void;
}

/**
 * Filter state + reload behaviour for the activity dialog.
 *
 * `updateFilter` mutates state; the effects below trigger the reload via
 * `router.reload` (no URL mutation, suitable for dialogs) with `search`
 * debounced and the other fields firing immediately.
 *
 * @param initialFilters  Sanitized filters emitted by the backend.
 * @param delay           Debounce delay in ms for the `search` field (default 500).
 */
export function useTrustedDeviceActivityFilters(
  initialFilters: TrustedDeviceActivityFilters,
  delay = 500,
): TrustedDeviceActivityFiltersController {
  const [filters, setFilters] = useState<TrustedDeviceActivityFilterState>(initialFilters);

  const [committedFilters, setCommittedFilters] =
    useState<TrustedDeviceActivityFilterState>(filters);

  const hasInteractedRef = useRef<boolean>(false);
  const filtersRef = useRef<TrustedDeviceActivityFilterState>(filters);

  useEffect(() => {
    filtersRef.current = filters;
  }, [filters]);

  const triggerReload = useCallback(
    (next: TrustedDeviceActivityFilterState, page?: number, onFinish?: () => void): void => {
      const action = next.action === null || next.action.length === 0 ? "" : next.action.join(",");

      const sinceDays =
        next.sinceDays === null || next.sinceDays.length === 0 ? "" : next.sinceDays.join(",");

      router.reload({
        data: {
          action,
          page: page ?? 1,
          search: next.search,
          since_days: sinceDays,
        },
        only: ["activityDialog"],
        preserveUrl: true,
        replace: true,
        onFinish: () => {
          setCommittedFilters(next);
          onFinish?.();
        },
      });
    },
    [],
  );

  const reload = useCallback(
    (onFinish?: () => void): void => {
      triggerReload(filtersRef.current, undefined, onFinish);
    },
    [triggerReload],
  );

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
    <K extends keyof TrustedDeviceActivityFilterState>(
      key: K,
      value: TrustedDeviceActivityFilterState[K],
    ): void => {
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

    setFilters(defaultTrustedDeviceActivityFilters);
  }, []);

  return {
    committedFilters,
    filters,
    goToPage,
    reload,
    resetFilters,
    updateFilter,
  };
}
