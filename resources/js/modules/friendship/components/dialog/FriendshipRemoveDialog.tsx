import type { JSX } from "react";
import { useId } from "react";

import { AlertTriangleIcon, UserMinusIcon } from "lucide-react";

import { Alert, AlertDescription } from "@/shared/components/shadcn/ui/alert";
import { Avatar, AvatarFallback, AvatarImage } from "@/shared/components/shadcn/ui/avatar";
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
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useDateTime, useTranslation } from "@/shared/hooks";

import { validImage } from "@/shared/lib";
import { alertVariants, buttonVariants } from "@/shared/lib/styling";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipPerson } from "../../types/friendshipSearch";

interface FriendshipRemoveDialogProps {
  isOpen: boolean;
  isRemoving: boolean;
  onConfirm: () => void;
  onOpenChange: (isOpen: boolean) => void;
  person: FriendshipPerson;
}

function FriendshipRemoveDialog(props: FriendshipRemoveDialogProps): JSX.Element {
  const { isOpen, isRemoving, onConfirm, onOpenChange, person } = props;

  const { t } = useTranslation();

  const handleOpenChange = (nextIsOpen: boolean): void => {
    if (isRemoving && !nextIsOpen) return;

    onOpenChange(nextIsOpen);
  };

  return (
    <Dialog onOpenChange={handleOpenChange} open={isOpen}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <UserMinusIcon aria-hidden="true" className="size-5 shrink-0 text-muted-foreground" />

            {t("friendship/button.remove_dialog.title", { name: person.name })}
          </DialogTitle>

          <DialogDescription>
            {t("friendship/button.remove_dialog.description", { name: person.name })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <FriendshipRemovePersonCard person={person} />

          <FriendshipRemoveConsequences name={person.name} />
        </div>

        <DialogFooter>
          <DialogClose asChild>
            <Button disabled={isRemoving} type="button" variant="outline">
              {t("friendship/button.remove_dialog.cancel")}
            </Button>
          </DialogClose>

          <Button
            className={buttonVariants.destructive}
            disabled={isRemoving}
            onClick={onConfirm}
            type="button"
            variant="destructive"
          >
            {isRemoving ? (
              <>
                <Spinner />
                {t("friendship/button.remove_dialog.removing")}
              </>
            ) : (
              t("friendship/button.remove_dialog.confirm")
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface FriendshipRemovePersonCardProps {
  person: FriendshipPerson;
}

function FriendshipRemovePersonCard(props: FriendshipRemovePersonCardProps): JSX.Element {
  const { person } = props;

  const { t, tChoice } = useTranslation();

  const { formatFullDate } = useDateTime();

  const { friendsSince = null, mutualFriendsCount = 0, name, profilePicture } = person;

  return (
    <div className="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
      <Avatar className="size-12">
        <AvatarImage
          alt=""
          className="object-cover"
          src={validImage(profilePicture ?? null, profilePicturePlaceholder)}
        />

        <AvatarFallback>{name.charAt(0).toUpperCase()}</AvatarFallback>
      </Avatar>

      <div className="flex min-w-0 flex-col gap-0.5">
        <p className="truncate text-sm font-medium">{name}</p>

        {friendsSince !== null && (
          <p className="text-xs text-muted-foreground">
            {t("friendship/button.remove_dialog.friends_since", {
              date: formatFullDate(friendsSince),
            })}
          </p>
        )}

        {mutualFriendsCount > 0 && (
          <p className="text-xs text-muted-foreground">
            {tChoice("friendship/button.remove_dialog.mutual_friends", mutualFriendsCount, {
              count: mutualFriendsCount,
            })}
          </p>
        )}
      </div>
    </div>
  );
}

interface FriendshipRemoveConsequencesProps {
  name: string;
}

function FriendshipRemoveConsequences(props: FriendshipRemoveConsequencesProps): JSX.Element {
  const { name } = props;

  const { t } = useTranslation();

  const titleId = useId();

  return (
    <Alert aria-labelledby={titleId} className={alertVariants.warning}>
      <AlertTriangleIcon aria-hidden="true" />

      <h3 className="col-start-2 min-h-4 text-sm font-medium tracking-tight" id={titleId}>
        {t("friendship/button.remove_dialog.consequences_title")}
      </h3>

      <AlertDescription>
        <ul className="mt-1 list-inside list-disc space-y-1.5">
          <li>{t("friendship/button.remove_dialog.consequences.feed", { name })}</li>

          <li>{t("friendship/button.remove_dialog.consequences.no_notification", { name })}</li>

          <li>{t("friendship/button.remove_dialog.consequences.request_again")}</li>
        </ul>
      </AlertDescription>
    </Alert>
  );
}

export default FriendshipRemoveDialog;
