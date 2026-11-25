import type { JSX } from "react";

import { Loader2Icon } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { useTranslation } from "@/shared/hooks";

interface FriendshipRemoveDialogProps {
  isOpen: boolean;
  isRemoving: boolean;
  onConfirm: () => void;
  onOpenChange: (isOpen: boolean) => void;
  userName: string;
}

function FriendshipRemoveDialog(props: FriendshipRemoveDialogProps): JSX.Element {
  const { t } = useTranslation();
  const { onConfirm, onOpenChange, isOpen, isRemoving, userName } = props;

  return (
    <Dialog onOpenChange={onOpenChange} open={isOpen}>
      <DialogContent showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>
            {t("friendship/button.remove_dialog.title", { name: userName })}
          </DialogTitle>

          <DialogDescription>{t("friendship/button.remove_dialog.description")}</DialogDescription>
        </DialogHeader>

        <DialogFooter>
          <DialogClose asChild>
            <Button disabled={isRemoving} type="button" variant="outline">
              {t("friendship/button.remove_dialog.cancel")}
            </Button>
          </DialogClose>

          <Button disabled={isRemoving} onClick={onConfirm} type="button" variant="destructive">
            {isRemoving && <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />}

            {t("friendship/button.remove_dialog.confirm")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default FriendshipRemoveDialog;
