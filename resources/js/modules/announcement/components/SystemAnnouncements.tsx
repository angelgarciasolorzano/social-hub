import type { JSX } from "react";

import AnnouncementBanner from "@/modules/announcement/components/AnnouncementBanner";
import { useAnnouncementDismiss } from "@/modules/announcement/hooks/useAnnouncementDismiss";
import type { Announcement } from "@/modules/announcement/types/announcement";

interface SystemAnnouncementsProps {
  initialAnnouncements: Announcement[];
}

/**
 * The announcement banner mounted once for the whole application, beside every page.
 */
function SystemAnnouncements({
  initialAnnouncements,
}: SystemAnnouncementsProps): JSX.Element | null {
  const { dismissAnnouncement, dismissingAnnouncementIds, visibleAnnouncements } =
    useAnnouncementDismiss(initialAnnouncements);

  return (
    <AnnouncementBanner
      announcements={visibleAnnouncements}
      dismissingAnnouncementIds={dismissingAnnouncementIds}
      onDismiss={dismissAnnouncement}
    />
  );
}

export default SystemAnnouncements;
