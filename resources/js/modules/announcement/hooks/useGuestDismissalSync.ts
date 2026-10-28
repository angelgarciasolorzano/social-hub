import { useEffect, useRef } from "react";

import { useHttp } from "@inertiajs/react";

import {
  clearGuestDismissedIds,
  readGuestDismissedIds,
} from "@/modules/announcement/utils/announcementDismissalStorage";

import { sync } from "@/shared/wayfinder/actions/App/Modules/Announcement/Controllers/AnnouncementDismissalController";

const MAX_SYNCED_ANNOUNCEMENT_IDS = 20;

interface DismissalSync {
  announcement_ids: number[];
}

/**
 * Once a guest signs in, send the announcements they closed in this browser so they stay closed on every device.
 * It runs once per sign-in, and a failed request keeps the list for the next page load without bothering the user.
 *
 * @param isAuthenticated Whether the current page has a signed-in user.
 */
export function useGuestDismissalSync(isAuthenticated: boolean): void {
  const hasSyncedRef = useRef<boolean>(false);

  const { post, transform } = useHttp<DismissalSync, { dismissed: number[] }>({
    announcement_ids: [],
  });

  useEffect(() => {
    if (!isAuthenticated) {
      hasSyncedRef.current = false;

      return;
    }

    const guestDismissedIds = readGuestDismissedIds().slice(0, MAX_SYNCED_ANNOUNCEMENT_IDS);

    if (hasSyncedRef.current || guestDismissedIds.length === 0) {
      return;
    }

    hasSyncedRef.current = true;
    transform(() => ({ announcement_ids: guestDismissedIds }));

    post(sync().url, {
      onSuccess: () => {
        clearGuestDismissedIds();
      },
      onHttpException: () => false,
      onNetworkError: () => false,
    }).catch(() => undefined);
  }, [isAuthenticated, post, transform]);
}
