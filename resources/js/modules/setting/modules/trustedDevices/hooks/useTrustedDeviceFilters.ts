import { useCallback, useEffect, useState } from "react";

import { router } from "@inertiajs/react";

import { useDebounceCallback } from "usehooks-ts";

import type { TrustedDeviceFilters } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

/** Strip null/empty fields so the URL stays clean (e.g. `?browser=chrome`). */
function toQueryBag(filters: TrustedDeviceFilters): Record<string, string> {
  const bag: Record<string, string> = {};

  for (const [key, value] of Object.entries(filters)) {
    if (value !== null && value !== "") {
      bag[key] = String(value);
    }
  }

  return bag;
}

interface UseTrustedDeviceFiltersApi {
  filters: TrustedDeviceFilters;
  resetFilters: () => void;
  setFilters: (next: TrustedDeviceFilters) => void;
  updateFilter: <K extends keyof TrustedDeviceFilters>(
    key: K,
    value: TrustedDeviceFilters[K],
  ) => void;
}

/**
 * Filter state + debounced reload for the trusted-devices page.
 * Initial state comes from the backend's sanitized `filters` prop.
 */
export function useTrustedDeviceFilters(
  initialFilters: TrustedDeviceFilters,
  delay = 500,
): UseTrustedDeviceFiltersApi {
  const [filters, setFiltersState] = useState<TrustedDeviceFilters>(initialFilters);

  const reload = useDebounceCallback((next: TrustedDeviceFilters) => {
    router.get(index().url, toQueryBag(next), {
      only: ["trustedDevices"],
      preserveState: true,
      preserveScroll: true,
    });
  }, delay);

  useEffect(() => {
    reload(filters);
  }, [filters, reload]);

  const setFilters = useCallback((next: TrustedDeviceFilters) => {
    setFiltersState(next);
  }, []);

  const resetFilters = useCallback(() => {
    setFiltersState({ ...initialFilters, search: "", status: null, browser: null });
  }, [initialFilters]);

  const updateFilter = useCallback(
    <K extends keyof TrustedDeviceFilters>(key: K, value: TrustedDeviceFilters[K]) => {
      setFiltersState((prev) => ({ ...prev, [key]: value }));
    },
    [],
  );

  return { filters, resetFilters, setFilters, updateFilter };
}
