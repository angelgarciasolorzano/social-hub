import { useEffect } from "react";

import { router } from "@inertiajs/react";

import { useDebounceCallback } from "usehooks-ts";

import { index } from "@/shared/wayfinder/actions/App/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController";

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
 * Trigger a partial visit whenever any field in `filters` changes, debounced
 * by `delay` ms. Only non-empty fields are sent to the backend as query
 * string params. The browser URL is synced automatically because `router.get`
 * is told the full URL ( pathname + data ) explicitly — unlike `router.reload`
 * which only updates the URL when new params are added.
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

    router.get(index().url, data, {
      only: ["trustedDevices"],
      preserveState: true,
      preserveScroll: true,
    });
  }, delay);

  useEffect(() => {
    reload(filters);
  }, [filters, reload]);
}
