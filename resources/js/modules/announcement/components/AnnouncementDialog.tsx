import type { JSX } from "react";

import {
  AnnouncementBody,
  AnnouncementButton,
  announcementTypeStyles,
} from "@/modules/announcement/components/AnnouncementContent";
import type { Announcement } from "@/modules/announcement/types/announcement";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib/utils";

interface AnnouncementDialogProps {
  announcement: Announcement;
  onClose: (announcement: Announcement) => void;
}

function AnnouncementDialog({ announcement, onClose }: AnnouncementDialogProps): JSX.Element {
  const { className, Icon } = announcementTypeStyles[announcement.type];

  const close = (): void => {
    onClose(announcement);
  };

  return (
    <Dialog
      onOpenChange={(isOpen) => {
        if (!isOpen) {
          close();
        }
      }}
      open
    >
      <DialogContent className={cn("sm:max-w-lg", className)} showCloseButton={false}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Icon aria-hidden="true" className="size-5 shrink-0" />
            {announcement.title}
          </DialogTitle>
        </DialogHeader>

        {announcement.imageUrl !== null && (
          <img
            alt=""
            className="max-h-48 w-full rounded-md object-cover"
            src={announcement.imageUrl}
          />
        )}

        <DialogDescription asChild className="text-current">
          <div>
            <AnnouncementBody announcement={announcement} />
          </div>
        </DialogDescription>

        <DialogFooter>
          <AnnouncementButton announcement={announcement} onAction={close} />

          <Button onClick={close} size="sm" type="button">
            Entendido
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default AnnouncementDialog;
