import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";
import { BellOffIcon, EyeOffIcon, UserMinusIcon, UserPlusIcon } from "lucide-react";

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
import { buttonVariants } from "@/shared/lib/styling";

import { profilePicturePlaceholder } from "@/shared/assets";

import type { FriendshipPerson } from "../../types/friendshipSearch";

interface RemoveConsequence {
  descriptionKey: string;
  icon: LucideIcon;
  titleKey: string;
}

const REMOVE_CONSEQUENCES: RemoveConsequence[] = [
  {
    descriptionKey: "friendship/button.remove_dialog.consequences.feed",
    icon: EyeOffIcon,
    titleKey: "friendship/button.remove_dialog.consequences.feed_title",
  },
  {
    descriptionKey: "friendship/button.remove_dialog.consequences.no_notification",
    icon: BellOffIcon,
    titleKey: "friendship/button.remove_dialog.consequences.no_notification_title",
  },
  {
    descriptionKey: "friendship/button.remove_dialog.consequences.request_again",
    icon: UserPlusIcon,
    titleKey: "friendship/button.remove_dialog.consequences.request_again_title",
  },
];

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
      <DialogContent className="gap-6 pt-8 sm:max-w-md">
        <FriendshipRemoveHeader person={person} />

        <ul className="flex flex-col gap-6 border-t pt-5">
          {REMOVE_CONSEQUENCES.map((removeConsequence) => (
            <FriendshipRemoveConsequenceItem
              key={removeConsequence.titleKey}
              name={person.name}
              removeConsequence={removeConsequence}
            />
          ))}
        </ul>

        <DialogFooter className="grid grid-cols-2 gap-2 sm:grid sm:grid-cols-2">
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

interface FriendshipRemoveHeaderProps {
  person: FriendshipPerson;
}

function FriendshipRemoveHeader(props: FriendshipRemoveHeaderProps): JSX.Element {
  const { person } = props;

  const { t, tChoice } = useTranslation();

  const { formatFullDate } = useDateTime();

  const { friendsSince = null, mutualFriendsCount = 0, name, profilePicture } = person;

  const personDetails = [
    friendsSince !== null
      ? t("friendship/button.remove_dialog.friends_since", { date: formatFullDate(friendsSince) })
      : null,
    mutualFriendsCount > 0
      ? tChoice("friendship/button.remove_dialog.mutual_friends", mutualFriendsCount, {
          count: mutualFriendsCount,
        })
      : null,
  ].filter((personDetail) => personDetail !== null);

  return (
    <DialogHeader className="items-center gap-3.5 text-center sm:text-center">
      <div className="relative">
        <Avatar className="size-18 ring-4 ring-background">
          <AvatarImage
            alt=""
            className="object-cover"
            src={validImage(profilePicture ?? null, profilePicturePlaceholder)}
          />

          <AvatarFallback>{name.charAt(0).toUpperCase()}</AvatarFallback>
        </Avatar>

        <span
          aria-hidden="true"
          className="absolute -right-1 -bottom-1 flex size-7 items-center justify-center rounded-full bg-red-600 text-white ring-4 ring-background dark:bg-red-700"
        >
          <UserMinusIcon className="size-3.5" strokeWidth={2.25} />
        </span>
      </div>

      <div className="flex flex-col gap-1.5">
        <DialogTitle className="text-lg leading-snug">
          {t("friendship/button.remove_dialog.title", { name })}
        </DialogTitle>

        <DialogDescription>
          {personDetails.length > 0
            ? personDetails.join(" · ")
            : t("friendship/button.remove_dialog.description", { name })}
        </DialogDescription>
      </div>
    </DialogHeader>
  );
}

interface FriendshipRemoveConsequenceItemProps {
  name: string;
  removeConsequence: RemoveConsequence;
}

function FriendshipRemoveConsequenceItem(props: FriendshipRemoveConsequenceItemProps): JSX.Element {
  const { name, removeConsequence } = props;

  const { t } = useTranslation();

  const { descriptionKey, icon: ConsequenceIcon, titleKey } = removeConsequence;

  return (
    <li className="flex items-start gap-3">
      <span
        aria-hidden="true"
        className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
      >
        <ConsequenceIcon className="size-4" />
      </span>

      <div className="flex flex-col gap-0.5">
        <span className="text-sm font-medium">{t(titleKey)}</span>

        <span className="text-sm text-muted-foreground">{t(descriptionKey, { name })}</span>
      </div>
    </li>
  );
}

export default FriendshipRemoveDialog;
