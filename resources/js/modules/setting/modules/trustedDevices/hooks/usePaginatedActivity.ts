import { useCallback, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import type { TrustedDeviceActivityEvent } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

interface UsePaginatedActivityReturn {
  events: TrustedDeviceActivityEvent[];
  hasMore: boolean;
  isLoading: boolean;
  loadMore: () => void;
  reload: (filters: { action: string; sinceDays: string }) => void;
}

interface PaginatedActivity {
  data: TrustedDeviceActivityEvent[];
  next_page_url: string | null;
}

export function usePaginatedActivity(initial: PaginatedActivity): UsePaginatedActivityReturn {
  const [page, setPage] = useState<PaginatedActivity>(initial);
  const [isLoading, setIsLoading] = useState<boolean>(false);

  const loadingRef = useRef<boolean>(false);
  const lastRequestedUrl = useRef<string | null>(null);

  const nextUrl = page.next_page_url;
  const hasMore = nextUrl !== null;

  const loadMore = useCallback(() => {
    if (loadingRef.current || !nextUrl) return;
    if (lastRequestedUrl.current === nextUrl) return;

    loadingRef.current = true;
    lastRequestedUrl.current = nextUrl;
    setIsLoading(true);

    router.get(
      nextUrl,
      {},
      {
        only: ["activityLog"],
        preserveState: true,
        preserveUrl: true,
        preserveScroll: true,
        onSuccess: (page) => {
          const incoming = (page.props as { activityLog?: PaginatedActivity }).activityLog;

          if (!incoming) return;

          setPage((previous) => {
            const map = new Map<number, TrustedDeviceActivityEvent>();

            previous.data.forEach((event) => map.set(event.id, event));
            incoming.data.forEach((event) => map.set(event.id, event));

            return {
              ...incoming,
              data: Array.from(map.values()),
            };
          });
        },
        onFinish: () => {
          loadingRef.current = false;
          setIsLoading(false);
        },
      },
    );
  }, [nextUrl]);

  const reload = useCallback((filters: { action: string; sinceDays: string }) => {
    router.reload({
      data: {
        action: filters.action === "" ? undefined : filters.action,
        since_days: filters.sinceDays,
      },
      replace: true,
      preserveUrl: true,
      only: ["activityLog"],
      onSuccess: (page) => {
        const incoming = (page.props as { activityLog?: PaginatedActivity }).activityLog;

        if (!incoming) return;

        setPage(incoming);
        lastRequestedUrl.current = null;
      },
    });
  }, []);

  return {
    events: page.data,
    hasMore,
    isLoading,
    loadMore,
    reload,
  };
}

export type { PaginatedActivity };
