import type { JSX } from "react";

import {
  LuChevronDown,
  LuUserRoundCheck,
  LuUserRoundMinus,
  LuUserRoundPlus,
  LuUserRoundX,
} from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";

import { useTranslation } from "@/shared/hooks";

import { buttonVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import { useFriendshipButton } from "../../hooks/ui/useFriendshipButton";
import type { FriendshipState } from "../../types/friendshipSearch";
import FriendshipRemoveDialog from "../dialog/FriendshipRemoveDialog";

interface FriendshipButtonProps {
  friendshipId: null | number;
  friendshipState: FriendshipState;
  friendsSince?: null | string;
  fullWidth?: boolean;
  mutualFriendsCount?: number;
  onChange?: (friendshipState: FriendshipState, friendshipId: null | number) => void;
  profilePicture?: null | string;
  userId: number;
  userName: string;
}

function FriendshipButton(props: FriendshipButtonProps): JSX.Element {
  const {
    friendshipId,
    friendshipState,
    friendsSince,
    fullWidth = false,
    mutualFriendsCount,
    onChange,
    profilePicture,
    userId,
    userName,
  } = props;

  const { t } = useTranslation();

  const {
    friendshipState: currentFriendshipState,
    isProcessing,
    isRemoveDialogOpen,
    pendingAction,
    processingLabel,
    requestError,
    runAction,
    setIsRemoveDialogOpen,
  } = useFriendshipButton({ friendshipId, friendshipState, onChange, userId, userName });

  return (
    <div className={cn("flex flex-col gap-1", fullWidth ? "w-full items-stretch" : "items-end")}>
      <div aria-busy={pendingAction !== null} className={cn("flex gap-2", fullWidth && "w-full")}>
        {currentFriendshipState === "none" && (
          <Button
            aria-label={processingLabel("send")}
            disabled={isProcessing}
            onClick={() => {
              runAction("send");
            }}
            size="sm"
            type="button"
            variant="secondary"
          >
            <ActionIcon icon={LuUserRoundPlus} isPending={pendingAction === "send"} />
            {t("friendship/button.states.add")}
          </Button>
        )}

        {currentFriendshipState === "request_received" && (
          <>
            <Button
              aria-label={processingLabel("accept")}
              className={cn(
                buttonVariants.blue,
                "focus-visible:ring-blue-600/50",
                fullWidth && "flex-1",
                pendingAction === "accept" && "disabled:opacity-100",
              )}
              disabled={isProcessing}
              onClick={() => {
                runAction("accept");
              }}
              size="sm"
              type="button"
            >
              <ActionIcon icon={LuUserRoundCheck} isPending={pendingAction === "accept"} />
              {t("friendship/button.states.accept")}
            </Button>

            <Button
              aria-label={processingLabel("decline")}
              className={cn(fullWidth && "flex-1")}
              disabled={isProcessing}
              onClick={() => {
                runAction("decline");
              }}
              size="sm"
              type="button"
              variant="outline"
            >
              <ActionIcon icon={LuUserRoundX} isPending={pendingAction === "decline"} />
              {t("friendship/button.states.decline")}
            </Button>
          </>
        )}

        {currentFriendshipState === "request_sent" && (
          <FriendshipMenu
            actionIcon={LuUserRoundX}
            actionLabel={t("friendship/button.menu.cancel_request")}
            isPending={pendingAction === "cancel"}
            isProcessing={isProcessing}
            menuLabel={
              processingLabel("cancel") ?? t("friendship/button.menu.label", { name: userName })
            }
            onSelect={() => {
              runAction("cancel");
            }}
            stateIcon={LuUserRoundPlus}
            stateLabel={t("friendship/button.states.request_sent")}
          />
        )}

        {currentFriendshipState === "friends" && (
          <FriendshipMenu
            actionIcon={LuUserRoundMinus}
            actionLabel={t("friendship/button.menu.remove_friend")}
            isPending={pendingAction === "remove"}
            isProcessing={isProcessing}
            menuLabel={
              processingLabel("remove") ?? t("friendship/button.menu.label", { name: userName })
            }
            onSelect={() => {
              setIsRemoveDialogOpen(true);
            }}
            stateIcon={LuUserRoundCheck}
            stateLabel={t("friendship/button.states.friends")}
          />
        )}
      </div>

      {requestError && (
        <span className="max-w-64 text-right text-xs text-destructive" role="alert">
          {requestError}
        </span>
      )}

      <FriendshipRemoveDialog
        isOpen={isRemoveDialogOpen}
        isRemoving={pendingAction === "remove"}
        onConfirm={() => {
          runAction("remove");
        }}
        onOpenChange={setIsRemoveDialogOpen}
        person={{ friendsSince, mutualFriendsCount, name: userName, profilePicture }}
      />
    </div>
  );
}

interface FriendshipMenuProps {
  actionIcon: typeof LuUserRoundX;
  actionLabel: string;
  isPending: boolean;
  isProcessing: boolean;
  menuLabel: string;
  onSelect: () => void;
  stateIcon: typeof LuUserRoundX;
  stateLabel: string;
}

function FriendshipMenu(props: FriendshipMenuProps): JSX.Element {
  const {
    actionIcon: ActionMenuIcon,
    actionLabel,
    isPending,
    isProcessing,
    menuLabel,
    onSelect,
    stateIcon,
    stateLabel,
  } = props;

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          aria-label={menuLabel}
          disabled={isProcessing}
          size="sm"
          type="button"
          variant="secondary"
        >
          <ActionIcon icon={stateIcon} isPending={isPending} />
          {stateLabel}
          <LuChevronDown aria-hidden="true" className="size-4" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end">
        <DropdownMenuItem
          className="text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500"
          onSelect={onSelect}
        >
          <ActionMenuIcon aria-hidden="true" className="size-4 text-red-600 dark:text-red-500" />
          {actionLabel}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

interface ActionIconProps {
  icon: typeof LuUserRoundX;
  isPending: boolean;
}

function ActionIcon(props: ActionIconProps): JSX.Element {
  const { icon: Icon, isPending } = props;

  return isPending ? (
    <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
  ) : (
    <Icon aria-hidden="true" className="size-4" />
  );
}

export default FriendshipButton;
