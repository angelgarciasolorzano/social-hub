import { useEffect, useState } from "react";

import { router, useHttp } from "@inertiajs/react";

import { toast } from "sonner";

import type { Announcement } from "@/modules/announcement/types/announcement";

import AnnouncementDismissalController from "@/shared/wayfinder/actions/App/Modules/Announcement/Controllers/AnnouncementDismissalController";

const TOO_MANY_REQUESTS_STATUS = 429;

interface DismissedAnnouncements {
  dismissedAnnouncementIds: number[];
}

interface UseAnnouncementDismissReturn {
  dismissAnnouncement: (announcement: Announcement) => void;
  dismissingAnnouncementIds: number[];
  visibleAnnouncements: Announcement[];
}

/**
 * Keep the shared announcements in sync across visits and dismiss one at a time: it disappears at once and comes back
 * with an error toast if the request fails. The banner lives outside the page, so the props come from the first page
 * and then from each `navigate` event instead of `usePage()`.
 */
export function useAnnouncementDismiss(
  initialAnnouncements: Announcement[],
): UseAnnouncementDismissReturn {
  const [announcements, setAnnouncements] = useState<Announcement[]>(initialAnnouncements);
  const [pendingAnnouncementId, setPendingAnnouncementId] = useState<number | null>(null);

  const { data, post, processing } = useHttp<DismissedAnnouncements, { dismissed: boolean }>({
    dismissedAnnouncementIds: [],
  });

  useEffect(() => {
    return router.on("navigate", (event) => {
      setAnnouncements(sharedAnnouncementsOf(event.detail.page.props));
    });
  }, []);

  const dismissAnnouncement = (announcement: Announcement): void => {
    if (processing) return;

    setPendingAnnouncementId(announcement.id);

    post(AnnouncementDismissalController.url(announcement.id), {
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
  };

  return {
    dismissAnnouncement,
    dismissingAnnouncementIds: pendingAnnouncementId === null ? [] : [pendingAnnouncementId],
    visibleAnnouncements: announcements.filter(
      (announcement) => !data.dismissedAnnouncementIds.includes(announcement.id),
    ),
  };
}

/**
 * The `announcements` shared prop of a page, or none when the page did not receive it.
 */
export function sharedAnnouncementsOf(pageProps: Record<string, unknown>): Announcement[] {
  const announcements = pageProps["announcements"];

  return Array.isArray(announcements) ? (announcements as Announcement[]) : [];
}
