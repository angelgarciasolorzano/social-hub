import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { Info, type LucideIcon, OctagonAlert, TriangleAlert } from "lucide-react";

import {
  type Announcement,
  AnnouncementType,
  type AnnouncementTypeValues,
} from "@/modules/announcement/types/announcement";

import { Button } from "@/shared/components/shadcn/ui/button";

interface AnnouncementContentProps {
  announcement: Announcement;
}

function AnnouncementContent({ announcement }: AnnouncementContentProps): JSX.Element {
  return (
    <div className="flex w-full gap-3">
      {announcement.imageUrl !== null && (
        <img
          alt=""
          className="hidden size-14 shrink-0 rounded-md object-cover sm:block"
          src={announcement.imageUrl}
        />
      )}
      <div className="min-w-0 flex-1 space-y-2">
        <AnnouncementBody announcement={announcement} />
        <AnnouncementButton announcement={announcement} />
      </div>
    </div>
  );
}

interface AnnouncementBodyProps {
  announcement: Announcement;
}

export function AnnouncementBody({ announcement }: AnnouncementBodyProps): JSX.Element {
  return (
    <div
      className="[&_a]:underline [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
      dangerouslySetInnerHTML={{ __html: announcement.bodyHtml }}
    />
  );
}

interface AnnouncementButtonProps {
  announcement: Announcement;
  onAction?: () => void;
}

export function AnnouncementButton({
  announcement,
  onAction,
}: AnnouncementButtonProps): JSX.Element | null {
  const { buttonLabel, buttonUrl } = announcement;

  if (buttonLabel === null || buttonUrl === null) {
    return null;
  }

  return (
    <Button asChild size="sm" variant="outline">
      {buttonUrl.startsWith("/") ? (
        <Link href={buttonUrl} onClick={onAction}>
          {buttonLabel}
        </Link>
      ) : (
        <a href={buttonUrl} onClick={onAction} rel="noopener noreferrer" target="_blank">
          {buttonLabel}
        </a>
      )}
    </Button>
  );
}

interface AnnouncementTypeStyle {
  className: string;
  Icon: LucideIcon;
}

export const announcementTypeStyles: Record<AnnouncementTypeValues, AnnouncementTypeStyle> = {
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

export default AnnouncementContent;
