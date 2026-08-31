import { useCallback, useEffect, useMemo, useRef, useState } from "react";

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
  const [search, setSearch] = useState<string>(initialFilters.search);
  const [status, setStatus] = useState<TrustedDeviceStatusFilter | null>(initialFilters.status);
  const [browser, setBrowser] = useState<TrustedDeviceBrowserFilter | null>(initialFilters.browser);
  const [sort, setSort] = useState<TrustedDeviceSortKey>(initialFilters.sort);
  const [perPage, setPerPage] = useState<TrustedDevicePerPage>(initialFilters.perPage);

  const isFirstRenderRef = useRef<boolean>(true);

  const triggerReload = (filters: TrustedDeviceFilters): void => {
    router.get(index().url, toQueryBag(filters), {
      only: ["trustedDevices"],
      preserveState: true,
      preserveScroll: true,
    });
  };

  const filters: TrustedDeviceFilterState = useMemo(
    () => ({ search, status, browser, sort, perPage }),
    [search, status, browser, sort, perPage],
  );

  const debouncedSearchReload = useDebounceCallback((next: TrustedDeviceFilterState) => {
    triggerReload(next);
  }, delay);

  useEffect(() => {
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false;

      return;
    }

    debouncedSearchReload(filters);
  }, [search, debouncedSearchReload, filters]);

  const updateFilter = useCallback(
    <K extends keyof TrustedDeviceFilterState>(
      key: K,
      value: TrustedDeviceFilterState[K],
    ): void => {
      switch (key) {
        case "search": {
          setSearch(value as string);

          break;
        }

        case "status": {
          const nextStatus = value as TrustedDeviceStatusFilter | null;

          setStatus(nextStatus);
          triggerReload({ search, status: nextStatus, browser, sort, perPage });

          break;
        }

        case "browser": {
          const nextBrowser = value as TrustedDeviceBrowserFilter | null;

          setBrowser(nextBrowser);
          triggerReload({ search, status, browser: nextBrowser, sort, perPage });

          break;
        }

        case "sort": {
          const nextSort = value as TrustedDeviceSortKey;

          setSort(nextSort);
          triggerReload({ search, status, browser, sort: nextSort, perPage });

          break;
        }

        case "perPage": {
          const nextPerPage = value as TrustedDevicePerPage;

          setPerPage(nextPerPage);
          triggerReload({ search, status, browser, sort, perPage: nextPerPage });

          break;
        }
      }
    },
    [search, status, browser, sort, perPage],
  );

  const resetFilters = useCallback(() => {
    setSearch("");
    setStatus(null);
    setBrowser(null);
    triggerReload({ search: "", status: null, browser: null, sort, perPage });
  }, [sort, perPage]);

  return { filters, resetFilters, updateFilter };
}

/** Strip null/empty fields and camelCase → snake_case (e.g. `perPage` → `per_page`) so the URL matches the backend's wire format. */
function toQueryBag(filters: TrustedDeviceFilters): Record<string, string> {
  const bag: Record<string, string> = {};

  for (const [key, value] of Object.entries(filters)) {
    if (value === null || value === "") {
      continue;
    }

    const wireKey = key === "perPage" ? "per_page" : key;

    bag[wireKey] = String(value);
  }

  return bag;
}
