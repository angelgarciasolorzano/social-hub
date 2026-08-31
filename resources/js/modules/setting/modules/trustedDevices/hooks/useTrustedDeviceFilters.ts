import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { useDebounceCallback } from "usehooks-ts";

import {
  type TrustedDeviceBrowserFilter,
  type TrustedDevicePerPage,
  type TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import { type TrustedDeviceSortKey } from "@/modules/setting/modules/trustedDevices/data/trustedDeviceSort";
import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

export interface TrustedDeviceFilterState {
  browser: TrustedDeviceBrowserFilter | null;
  perPage: TrustedDevicePerPage;
  search: string;
  sort: TrustedDeviceSortKey;
  status: TrustedDeviceStatusFilter | null;
}

interface UseTrustedDeviceFiltersApi {
  filters: TrustedDeviceFilterState;
  resetFilters: () => void;
  updateFilter: <K extends keyof TrustedDeviceFilterState>(
    key: K,
    value: TrustedDeviceFilterState[K],
  ) => void;
}

/**
 * Filter state + per-field reload behaviour for the trusted-devices page.
 * Initial state comes from the backend's sanitized `filters` prop.
 *
 * @param initialFilters  Sanitized filters emitted by the backend.
 * @param delay           Debounce delay in ms for the `search` field (default 500).
 */
export function useTrustedDeviceFilters(
  initialFilters: TrustedDeviceFilters,
  delay = 500,
): UseTrustedDeviceFiltersApi {
  const [filters, setFilters] = useState<TrustedDeviceFilterState>(initialFilters);

  const isFirstSearchRenderRef = useRef<boolean>(true);

  const filtersRef = useRef<TrustedDeviceFilterState>(filters);

  useEffect(() => {
    filtersRef.current = filters;
  }, [filters]);

  const triggerReload = (next: TrustedDeviceFilterState): void => {
    router.get(index().url, toQueryBag(next), {
      only: ["trustedDevices"],
      preserveState: true,
      preserveScroll: true,
    });
  };

  const reloadSearchDebounced = useCallback((nextSearch: string) => {
    triggerReload({ ...filtersRef.current, search: nextSearch });
  }, []);

  const debouncedSearchReload = useDebounceCallback(reloadSearchDebounced, delay);

  const debouncedSearchReloadRef = useRef(debouncedSearchReload);

  useEffect(() => {
    debouncedSearchReloadRef.current = debouncedSearchReload;
  }, [debouncedSearchReload]);

  useEffect(() => {
    if (isFirstSearchRenderRef.current) {
      isFirstSearchRenderRef.current = false;
      return;
    }
    debouncedSearchReloadRef.current(filters.search);
  }, [filters.search]);

  const updateFilter = useCallback(
    <K extends keyof TrustedDeviceFilterState>(
      key: K,
      value: TrustedDeviceFilterState[K],
    ): void => {
      setFilters((prev) => ({ ...prev, [key]: value }));

      if (key !== "search") {
        triggerReload({ ...filtersRef.current, [key]: value });
      }
    },
    [],
  );

  const resetFilters = useCallback(() => {
    setFilters((prev) => ({ ...prev, search: "", status: null, browser: null }));

    triggerReload({
      ...filtersRef.current,
      search: "",
      status: null,
      browser: null,
    });
  }, []);

  return { filters, resetFilters, updateFilter };
}

function toQueryBag(filters: TrustedDeviceFilters): Record<string, string> {
  const bag: Record<string, string> = {};

  for (const [key, value] of Object.entries(filters)) {
    if (value === null || value === "") continue;

    const wireKey = key === "perPage" ? "per_page" : key;

    bag[wireKey] = String(value);
  }

  return bag;
}
