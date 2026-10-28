import type { JSX } from "react";

import { X } from "lucide-react";

import AnnouncementContent, {
  announcementTypeStyles,
} from "@/modules/announcement/components/AnnouncementContent";
import { type Announcement, AnnouncementType } from "@/modules/announcement/types/announcement";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";

import { cn } from "@/shared/lib/utils";

interface AnnouncementToastProps {
  announcement: Announcement;
  isDismissing: boolean;
  onDismiss: (announcement: Announcement) => void;
}

function AnnouncementToast({
  announcement,
  isDismissing,
  onDismiss,
}: AnnouncementToastProps): JSX.Element {
  const { className, Icon } = announcementTypeStyles[announcement.type];

  return (
    <Alert
      className={cn("relative w-full pr-12 shadow-lg", className)}
      role={announcement.type === AnnouncementType.CRITICAL ? "alert" : "status"}
    >
      <Icon aria-hidden="true" />

      <AlertTitle className="line-clamp-none">{announcement.title}</AlertTitle>

      <AlertDescription className="text-current">
        <AnnouncementContent announcement={announcement} />
      </AlertDescription>

      <Button
        aria-label={`Cerrar el aviso: ${announcement.title}`}
        className="absolute top-2 right-2 text-current hover:bg-current/10 hover:text-current"
        disabled={isDismissing}
        onClick={() => {
          onDismiss(announcement);
        }}
        size="icon-sm"
        type="button"
        variant="ghost"
      >
        <X />
      </Button>
    </Alert>
  );
}

export default AnnouncementToast;
