import { type CSSProperties, type JSX, useEffect, useRef } from "react";

import { toast } from "sonner";

import AnnouncementToast from "@/modules/announcement/components/AnnouncementToast";
import { useAnnouncementDismiss } from "@/modules/announcement/hooks/useAnnouncementDismiss";
import type { Announcement } from "@/modules/announcement/types/announcement";

import { Toaster } from "@/shared/components/shadcn/ui/sonner";

const ANNOUNCEMENT_TOASTER_ID = "announcements";

const MAX_VISIBLE_ANNOUNCEMENTS = 3;

const announcementToasterStyle = { "--width": "min(40rem, calc(100vw - 2rem))" } as CSSProperties;

interface SystemAnnouncementsProps {
  initialAnnouncements: Announcement[];
  initialIsAuthenticated: boolean;
}

function SystemAnnouncements({
  initialAnnouncements,
  initialIsAuthenticated,
}: SystemAnnouncementsProps): JSX.Element {
  const { closeAnnouncement, dismissingAnnouncementIds, toastAnnouncements } =
    useAnnouncementDismiss(initialAnnouncements, initialIsAuthenticated);
  const shownToastIdsRef = useRef<Set<string>>(new Set<string>());

  useEffect(() => {
    const visibleToastIds = new Set<string>(
      toastAnnouncements.map((announcement) => announcementToastId(announcement)),
    );

    for (const shownToastId of shownToastIdsRef.current) {
      if (!visibleToastIds.has(shownToastId)) {
        toast.dismiss(shownToastId);
      }
    }

    for (const announcement of [...toastAnnouncements].reverse()) {
      toast.custom(
        () => (
          <AnnouncementToast
            announcement={announcement}
            isDismissing={dismissingAnnouncementIds.includes(announcement.id)}
            onDismiss={closeAnnouncement}
          />
        ),
        {
          dismissible: false,
          duration: Infinity,
          id: announcementToastId(announcement),
          toasterId: ANNOUNCEMENT_TOASTER_ID,
        },
      );
    }

    shownToastIdsRef.current = visibleToastIds;
  }, [closeAnnouncement, dismissingAnnouncementIds, toastAnnouncements]);

  return (
    <Toaster
      containerAriaLabel="Avisos del sistema"
      expand
      id={ANNOUNCEMENT_TOASTER_ID}
      position="top-center"
      style={announcementToasterStyle}
      toastOptions={{ className: "w-(--width)", unstyled: true }}
      visibleToasts={MAX_VISIBLE_ANNOUNCEMENTS}
    />
  );
}

function announcementToastId(announcement: Announcement): string {
  return `announcement-${String(announcement.id)}`;
}

export default SystemAnnouncements;
