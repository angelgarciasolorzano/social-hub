import { useEffect } from "react";

import { router } from "@inertiajs/react";

import { useDebounceCallback } from "usehooks-ts";

export interface TrustedDeviceFilters {
  browser: string | null;
  deviceType: string | null;
  lastAccess: string | null;
  search: string;
  sort: string;
  status: string | null;
  [key: string]: string | null;
}

/**
 * Trigger a partial `router.reload` whenever any field in `filters` changes,
 * debounced by `delay` ms. Only non-empty fields are sent to the backend as
 * query string params to keep the URL clean. Only the `trustedDevices` prop
 * is re-fetched.
 *
 * @param filters  All filter / search / sort state in one object.
 * @param delay    Debounce delay in ms (default 500).
 */
export function useDebouncedReload(filters: TrustedDeviceFilters, delay = 500): void {
  const reload = useDebounceCallback((nextFilters: TrustedDeviceFilters) => {
    const data: Record<string, string> = {};

    for (const [key, value] of Object.entries(nextFilters)) {
      if (value !== null && value !== "") {
        data[key] = value;
      }
    }

    router.reload({
      only: ["trustedDevices"],
      data,
    });
  }, delay);

  useEffect(() => {
    reload(filters);
  }, [filters, reload]);
}
