import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { Info, type LucideIcon, OctagonAlert, TriangleAlert, X } from "lucide-react";

import {
  type Announcement,
  AnnouncementType,
  type AnnouncementTypeValues,
} from "@/modules/announcement/types/announcement";

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
      className={cn("relative w-full shadow-lg", announcement.isDismissible && "pr-12", className)}
      role={announcement.type === AnnouncementType.CRITICAL ? "alert" : "status"}
    >
      <Icon aria-hidden="true" />
      <AlertTitle className="line-clamp-none">{announcement.title}</AlertTitle>
      <AlertDescription className="text-current">
        <div className="flex w-full gap-3">
          {announcement.imageUrl !== null && (
            <img
              alt=""
              className="hidden size-14 shrink-0 rounded-md object-cover sm:block"
              src={announcement.imageUrl}
            />
          )}
          <div className="min-w-0 flex-1 space-y-2">
            <div
              className="[&_a]:underline [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
              dangerouslySetInnerHTML={{ __html: announcement.bodyHtml }}
            />

            <AnnouncementButton announcement={announcement} />
          </div>
        </div>
      </AlertDescription>
      {announcement.isDismissible && (
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
      )}
    </Alert>
  );
}

interface AnnouncementButtonProps {
  announcement: Announcement;
}

function AnnouncementButton({ announcement }: AnnouncementButtonProps): JSX.Element | null {
  const { buttonLabel, buttonUrl } = announcement;

  if (buttonLabel === null || buttonUrl === null) {
    return null;
  }

  return (
    <Button asChild size="sm" variant="outline">
      {buttonUrl.startsWith("/") ? (
        <Link href={buttonUrl}>{buttonLabel}</Link>
      ) : (
        <a href={buttonUrl} rel="noopener noreferrer" target="_blank">
          {buttonLabel}
        </a>
      )}
    </Button>
  );
}

const announcementTypeStyles: Record<
  AnnouncementTypeValues,
  { className: string; Icon: LucideIcon }
> = {
  [AnnouncementType.CRITICAL]: {
    className:
      "border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-50",
    Icon: OctagonAlert,
  },
  [AnnouncementType.INFO]: {
    className:
      "border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-50",
    Icon: Info,
  },
  [AnnouncementType.WARNING]: {
    className:
      "border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-50",
    Icon: TriangleAlert,
  },
};

export default AnnouncementToast;
