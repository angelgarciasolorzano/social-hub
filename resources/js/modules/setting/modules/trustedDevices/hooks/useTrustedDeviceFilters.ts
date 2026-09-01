import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import {
  type TrustedDeviceBrowserFilter,
  type TrustedDeviceDeviceTypeFilter,
  type TrustedDeviceLastAccessFilter,
  type TrustedDevicePerPage,
  type TrustedDeviceStatusFilter,
} from "@/modules/setting/modules/trustedDevices/data/trustedDeviceFilters";
import { type TrustedDeviceSortKey } from "@/modules/setting/modules/trustedDevices/data/trustedDeviceSort";
import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

export interface TrustedDeviceFilterState {
  browser: TrustedDeviceBrowserFilter[] | null;
  deviceType: TrustedDeviceDeviceTypeFilter | null;
  lastAccess: TrustedDeviceLastAccessFilter[] | null;
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
 * Filter state + reload behaviour for the trusted-devices page.
 *
 * `updateFilter` mutates state; the two effects below trigger the reload —
 * `search` is debounced, the other fields fire immediately.
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
  const isFirstNonSearchRenderRef = useRef<boolean>(true);
  const filtersRef = useRef<TrustedDeviceFilterState>(filters);

  useEffect(() => {
    filtersRef.current = filters;
  }, [filters]);

  const triggerReload = useCallback((next: TrustedDeviceFilterState): void => {
    router.get(index().url, toQueryBag(next), {
      only: ["trustedDevices"],
      preserveState: true,
      preserveScroll: true,
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
  }, [
    filters.status,
    filters.browser,
    filters.deviceType,
    filters.lastAccess,
    filters.sort,
    filters.perPage,
    triggerReload,
  ]);

  const updateFilter = useCallback(
    <K extends keyof TrustedDeviceFilterState>(
      key: K,
      value: TrustedDeviceFilterState[K],
    ): void => {
      setFilters((prev) => ({ ...prev, [key]: value }));
    },
    [],
  );

  const resetFilters = useCallback(() => {
    setFilters((prev) => ({
      ...prev,
      search: "",
      status: null,
      browser: null,
      deviceType: null,
      lastAccess: null,
    }));
  }, []);

  return { filters, resetFilters, updateFilter };
}

/** Serializes filters to query params, skipping empty values and converting camelCase keys to snake_case wire format. Multi-select values are joined as CSV. */
function toQueryBag(filters: TrustedDeviceFilters): Record<string, string> {
  const bag: Record<string, string> = {};

  for (const [key, value] of Object.entries(filters)) {
    if (value === null || value === "") continue;

    const wireKey = key.replaceAll(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);

    bag[wireKey] = Array.isArray(value) ? value.join(",") : String(value);
  }

  return bag;
}
