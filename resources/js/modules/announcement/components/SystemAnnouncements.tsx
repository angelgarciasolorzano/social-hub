import { type CSSProperties, type JSX, useEffect, useMemo, useRef } from "react";

import { toast } from "sonner";

import AnnouncementDialog from "@/modules/announcement/components/AnnouncementDialog";
import AnnouncementToast from "@/modules/announcement/components/AnnouncementToast";
import { useAnnouncementDismiss } from "@/modules/announcement/hooks/useAnnouncementDismiss";
import { useGuestDismissalSync } from "@/modules/announcement/hooks/useGuestDismissalSync";
import type { Announcement } from "@/modules/announcement/types/announcement";

import { Toaster } from "@/shared/components/shadcn/ui/sonner";

const ANNOUNCEMENT_TOASTER_ID = "announcements";

const MAX_VISIBLE_ANNOUNCEMENTS = 3;

const announcementToasterStyle = { "--width": "min(40rem, calc(100vw - 2rem))" } as CSSProperties;

interface SystemAnnouncementsProps {
  initialAnnouncements: Announcement[];
  initialIsAuthenticated: boolean;
}

/**
 * The system announcements of every page: toasts stacked in their own collapsed toaster, separate from the flash
 * toaster, and dialogs one at a time in the server's order (the most severe first); closing one shows the next.
 * Toasts wait while a dialog is open, since the toaster would otherwise be drawn above the dialog's backdrop.
 */
function SystemAnnouncements({
  initialAnnouncements,
  initialIsAuthenticated,
}: SystemAnnouncementsProps): JSX.Element {
  const {
    closeAnnouncement,
    dialogAnnouncements,
    dismissingAnnouncementIds,
    isAuthenticated,
    toastAnnouncements,
  } = useAnnouncementDismiss(initialAnnouncements, initialIsAuthenticated);

  const shownDialogAnnouncement = dialogAnnouncements.at(0);

  const shownToastAnnouncements = useMemo<Announcement[]>(
    () => (shownDialogAnnouncement === undefined ? toastAnnouncements : []),
    [shownDialogAnnouncement, toastAnnouncements],
  );

  useGuestDismissalSync(isAuthenticated);

  const shownToastIdsRef = useRef<Set<string>>(new Set<string>());

  useEffect(() => {
    const visibleToastIds = new Set<string>(
      shownToastAnnouncements.map((announcement) => announcementToastId(announcement)),
    );

    for (const shownToastId of shownToastIdsRef.current) {
      if (!visibleToastIds.has(shownToastId)) {
        toast.dismiss(shownToastId);
      }
    }

    for (const announcement of [...shownToastAnnouncements].reverse()) {
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
  }, [closeAnnouncement, dismissingAnnouncementIds, shownToastAnnouncements]);

  return (
    <>
      <Toaster
        containerAriaLabel="Avisos del sistema"
        expand={false}
        id={ANNOUNCEMENT_TOASTER_ID}
        position="top-center"
        style={announcementToasterStyle}
        toastOptions={{ className: "w-(--width)", unstyled: true }}
        visibleToasts={MAX_VISIBLE_ANNOUNCEMENTS}
      />

      {shownDialogAnnouncement !== undefined && (
        <AnnouncementDialog
          announcement={shownDialogAnnouncement}
          key={shownDialogAnnouncement.id}
          onClose={closeAnnouncement}
        />
      )}
    </>
  );
}

function announcementToastId(announcement: Announcement): string {
  return `announcement-${String(announcement.id)}`;
}

export default SystemAnnouncements;
