import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { defaultTrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters";
import type { TrustedDevicePerPage } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import type { TrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";

export interface TrustedDeviceActivityFilterState extends TrustedDeviceActivityFilters {
  perPage: TrustedDevicePerPage;
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
 * Filter and page-size state + reload behaviour for the activity dialog.
 *
 * `updateFilter` mutates state; the effects below trigger the reload via
 * `router.reload` (no URL mutation, suitable for dialogs) with `search`
 * debounced and the other fields firing immediately.
 *
 * @param initialFilters  Initial activity filters and page size.
 * @param delay           Debounce delay in ms for the `search` field (default 500).
 */
export function useTrustedDeviceActivityFilters(
  initialFilters: TrustedDeviceActivityFilterState,
  delay = 500,
): TrustedDeviceActivityFiltersController {
  const [filters, setFilters] = useState<TrustedDeviceActivityFilterState>(initialFilters);

  const [committedFilters, setCommittedFilters] =
    useState<TrustedDeviceActivityFilterState>(filters);

  const hasInteractedRef = useRef<boolean>(false);
  const filtersRef = useRef<TrustedDeviceActivityFilterState>(filters);
  const currentPageRef = useRef<number>(1);

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
          page: page ?? currentPageRef.current,
          per_page: next.perPage,
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
  }, [filters.action, filters.sinceDays, filters.perPage, triggerReload]);

  const updateFilter = useCallback(
    <K extends keyof TrustedDeviceActivityFilterState>(
      key: K,
      value: TrustedDeviceActivityFilterState[K],
    ): void => {
      hasInteractedRef.current = true;
      currentPageRef.current = 1;

      setFilters((prev) => ({ ...prev, [key]: value }));
    },
    [],
  );

  const goToPage = useCallback(
    (page: number): void => {
      currentPageRef.current = page;

      triggerReload(filtersRef.current, page);
    },
    [triggerReload],
  );

  const resetFilters = useCallback(() => {
    hasInteractedRef.current = true;
    currentPageRef.current = 1;

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
