import { useCallback, useEffect, useMemo, useState } from "react";

import { router, useHttp } from "@inertiajs/react";

import { toast } from "sonner";

import { type Announcement, AnnouncementDisplay } from "@/modules/announcement/types/announcement";
import {
  addGuestDismissedId,
  addSessionDismissedId,
  readGuestDismissedIds,
  readSessionDismissedIds,
} from "@/modules/announcement/utils/announcementDismissalStorage";

import { dismiss } from "@/shared/wayfinder/actions/App/Modules/Announcement/Controllers/AnnouncementDismissalController";

const TOO_MANY_REQUESTS_STATUS = 429;

interface DismissedAnnouncements {
  dismissedAnnouncementIds: number[];
}

interface UseAnnouncementDismissReturn {
  closeAnnouncement: (announcement: Announcement) => void;
  dialogAnnouncements: Announcement[];
  dismissingAnnouncementIds: number[];
  isAuthenticated: boolean;
  toastAnnouncements: Announcement[];
}

/**
 * Keep the shared announcements and the session state in sync across visits, and close an announcement where it
 * belongs: on the server for a signed-in user and a dismissible announcement (it disappears at once and comes back
 * if the request fails), in `localStorage` for a guest, and in `sessionStorage` when it cannot be dismissed.
 *
 * @param initialAnnouncements The `announcements` prop of the first page.
 * @param initialIsAuthenticated Whether the first page has a signed-in user.
 * @returns The toasts and the dialogs left to show, in the server's order, and how to close one.
 */
export function useAnnouncementDismiss(
  initialAnnouncements: Announcement[],
  initialIsAuthenticated: boolean,
): UseAnnouncementDismissReturn {
  const [announcements, setAnnouncements] = useState<Announcement[]>(initialAnnouncements);
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(initialIsAuthenticated);
  const [locallyClosedIds, setLocallyClosedIds] = useState<number[]>(() => [
    ...readGuestDismissedIds(),
    ...readSessionDismissedIds(),
  ]);
  const [pendingAnnouncementId, setPendingAnnouncementId] = useState<null | number>(null);

  const { data, post, processing } = useHttp<DismissedAnnouncements, { dismissed: boolean }>({
    dismissedAnnouncementIds: [],
  });

  useEffect(() => {
    return router.on("navigate", (event) => {
      setAnnouncements(sharedAnnouncementsOf(event.detail.page.props));
      setIsAuthenticated(isAuthenticatedPage(event.detail.page.props));
    });
  }, []);

  const closeLocally = useCallback<(announcementId: number) => void>((announcementId) => {
    setLocallyClosedIds((closedIds) => [...closedIds, announcementId]);
  }, []);

  const dismissOnServer = useCallback<(announcement: Announcement) => void>(
    (announcement) => {
      if (processing) return;

      setPendingAnnouncementId(announcement.id);

      post(dismiss(announcement.id).url, {
        optimistic: (currentData) => ({
          dismissedAnnouncementIds: [...currentData.dismissedAnnouncementIds, announcement.id],
        }),
        onFinish: () => {
          setPendingAnnouncementId(null);
        },
        onHttpException: (response) => {
          toast.error(
            response.status === TOO_MANY_REQUESTS_STATUS
              ? "Demasiados intentos, espera un momento."
              : "No se pudo cerrar el aviso.",
          );

          return false;
        },
        onNetworkError: () => {
          toast.error("No se pudo cerrar el aviso.");

          return false;
        },
      }).catch(() => undefined);
    },
    [post, processing],
  );

  const closeAnnouncement = useCallback<(announcement: Announcement) => void>(
    (announcement) => {
      if (!announcement.isDismissible) {
        addSessionDismissedId(announcement.id);
        closeLocally(announcement.id);

        return;
      }

      if (!isAuthenticated) {
        addGuestDismissedId(announcement.id);
        closeLocally(announcement.id);

        return;
      }

      dismissOnServer(announcement);
    },
    [closeLocally, dismissOnServer, isAuthenticated],
  );

  const dismissingAnnouncementIds = useMemo<number[]>(
    () => (pendingAnnouncementId === null ? [] : [pendingAnnouncementId]),
    [pendingAnnouncementId],
  );

  const shownAnnouncements = useMemo<Announcement[]>(
    () =>
      announcements.filter(
        (announcement) =>
          !data.dismissedAnnouncementIds.includes(announcement.id) &&
          !locallyClosedIds.includes(announcement.id),
      ),
    [announcements, data.dismissedAnnouncementIds, locallyClosedIds],
  );

  const toastAnnouncements = useMemo<Announcement[]>(
    () =>
      shownAnnouncements.filter(
        (announcement) => announcement.display === AnnouncementDisplay.TOAST,
      ),
    [shownAnnouncements],
  );

  const dialogAnnouncements = useMemo<Announcement[]>(
    () =>
      shownAnnouncements.filter(
        (announcement) => announcement.display === AnnouncementDisplay.DIALOG,
      ),
    [shownAnnouncements],
  );

  return {
    closeAnnouncement,
    dialogAnnouncements,
    dismissingAnnouncementIds,
    isAuthenticated,
    toastAnnouncements,
  };
}

/**
 * Read the `announcements` shared prop of a page.
 *
 * @param pageProps The page props.
 * @returns The announcements, or none when the page did not receive them.
 */
export function sharedAnnouncementsOf(pageProps: Record<string, unknown>): Announcement[] {
  const announcements = pageProps["announcements"];

  return Array.isArray(announcements) ? (announcements as Announcement[]) : [];
}

/**
 * Check whether a page was rendered for a signed-in user.
 *
 * @param pageProps The page props.
 * @returns Whether the `auth.user` shared prop is set.
 */
export function isAuthenticatedPage(pageProps: Record<string, unknown>): boolean {
  const auth = pageProps["auth"];

  return typeof auth === "object" && auth !== null && "user" in auth && auth.user !== null;
}
