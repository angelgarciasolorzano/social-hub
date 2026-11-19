import type { JSX } from "react";
import { useState } from "react";

import { useHttp } from "@inertiajs/react";

import {
  LuChevronDown,
  LuUserRoundCheck,
  LuUserRoundMinus,
  LuUserRoundPlus,
  LuUserRoundX,
} from "react-icons/lu";

import { Loader2Icon } from "lucide-react";

import {
  accept,
  cancel,
  decline,
  destroy,
  store,
} from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipController";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/shared/components/shadcn/ui/dropdown-menu";

import { useTranslation } from "@/shared/hooks";

import type { FriendshipActionResponse, FriendshipState } from "../types/friendshipSearch";
import FriendshipRemoveDialog from "./FriendshipRemoveDialog";

type FriendshipAction = "accept" | "cancel" | "decline" | "remove" | "send";

interface FriendshipStatus {
  friendshipId: null | number;
  friendshipState: FriendshipState;
}

interface FriendshipButtonProps {
  friendshipId: null | number;
  friendshipState: FriendshipState;
  onChange?: (friendshipState: FriendshipState, friendshipId: null | number) => void;
  userId: number;
  userName: string;
}

/**
 * The friendship control of a person, one per state: add, a menu to cancel a sent request, accept or
 * decline a received one, and a menu to remove a friend after confirming. Each action shows its result
 * at once and is reverted if the server refuses it (409, 429) or the network fails; a refusal shows the
 * server's message and the state it stored, without Inertia's error modal. The initial state is read
 * once per mount.
 */
function FriendshipButton({
  friendshipId: initialFriendshipId,
  friendshipState: initialFriendshipState,
  onChange,
  userId,
  userName,
}: FriendshipButtonProps): JSX.Element {
  const { t } = useTranslation();
  const [friendshipStatus, setFriendshipStatus] = useState<FriendshipStatus>({
    friendshipId: initialFriendshipId,
    friendshipState: initialFriendshipState,
  });
  const [pendingAction, setPendingAction] = useState<FriendshipAction | null>(null);
  const [requestError, setRequestError] = useState<null | string>(null);
  const [isRemoveDialogOpen, setIsRemoveDialogOpen] = useState<boolean>(false);

  const friendshipRequest = useHttp<Record<string, never>, FriendshipActionResponse>(() => ({}));

  const applyServerStatus = (friendshipActionResponse: FriendshipActionResponse): void => {
    setFriendshipStatus({
      friendshipId: friendshipActionResponse.friendshipId,
      friendshipState: friendshipActionResponse.state,
    });
    onChange?.(friendshipActionResponse.state, friendshipActionResponse.friendshipId);
  };

  const runAction = (friendshipAction: FriendshipAction): void => {
    if (friendshipRequest.processing) return;

    const actionUrl = friendshipActionUrl(friendshipAction, userId, friendshipStatus.friendshipId);

    if (actionUrl === null) {
      setRequestError(t("friendship/button.error"));

      return;
    }

    const previousStatus = friendshipStatus;
    const revert = (): void => {
      setFriendshipStatus(previousStatus);
      setRequestError(t("friendship/button.error"));
    };

    setRequestError(null);
    setPendingAction(friendshipAction);
    setFriendshipStatus({
      friendshipId: friendshipStatus.friendshipId,
      friendshipState: optimisticFriendshipState(friendshipAction),
    });

    const send =
      friendshipAction === "send" || friendshipAction === "accept"
        ? friendshipRequest.post
        : friendshipRequest.delete;

    send(actionUrl, {
      onSuccess: (friendshipActionResponse) => {
        applyServerStatus(friendshipActionResponse);
        setIsRemoveDialogOpen(false);
      },
      onHttpException: (response) => {
        const friendshipActionResponse = parseFriendshipActionResponse(response.data);

        if (friendshipActionResponse === null) {
          revert();
        } else {
          applyServerStatus(friendshipActionResponse);
          setRequestError(friendshipActionResponse.message);
        }

        setIsRemoveDialogOpen(false);

        return false;
      },
      onNetworkError: () => {
        revert();

        return false;
      },
      onFinish: () => {
        setPendingAction(null);
      },
    }).catch(() => undefined);
  };

  const processingLabel = (friendshipAction: FriendshipAction): string | undefined =>
    pendingAction === friendshipAction
      ? t(processingLabelKey(friendshipAction), { name: userName })
      : undefined;

  return (
    <div className="flex flex-col items-end gap-1">
      <div aria-busy={pendingAction !== null} className="flex gap-2">
        {friendshipStatus.friendshipState === "none" && (
          <Button
            aria-label={processingLabel("send")}
            disabled={friendshipRequest.processing}
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

        {friendshipStatus.friendshipState === "request_received" && (
          <>
            <Button
              aria-label={processingLabel("accept")}
              disabled={friendshipRequest.processing}
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
              disabled={friendshipRequest.processing}
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

        {friendshipStatus.friendshipState === "request_sent" && (
          <FriendshipMenu
            actionIcon={LuUserRoundX}
            actionLabel={t("friendship/button.menu.cancel_request")}
            isPending={pendingAction === "cancel"}
            isProcessing={friendshipRequest.processing}
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

        {friendshipStatus.friendshipState === "friends" && (
          <FriendshipMenu
            actionIcon={LuUserRoundMinus}
            actionLabel={t("friendship/button.menu.remove_friend")}
            isPending={pendingAction === "remove"}
            isProcessing={friendshipRequest.processing}
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
        userName={userName}
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

/**
 * A button that shows the current state and opens a menu with the one action that undoes it. The menu is
 * not modal, so the remove dialog it opens gets the focus and the pointer back.
 */
function FriendshipMenu({
  actionIcon: ActionMenuIcon,
  actionLabel,
  isPending,
  isProcessing,
  menuLabel,
  onSelect,
  stateIcon,
  stateLabel,
}: FriendshipMenuProps): JSX.Element {
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
        <DropdownMenuItem onSelect={onSelect} variant="destructive">
          <ActionMenuIcon aria-hidden="true" className="size-4" />
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

function ActionIcon({ icon: Icon, isPending }: ActionIconProps): JSX.Element {
  return isPending ? (
    <Loader2Icon aria-hidden="true" className="size-4 animate-spin" />
  ) : (
    <Icon aria-hidden="true" className="size-4" />
  );
}

/**
 * The Wayfinder URL of an action: sending targets the person, the others the pair's row, so they need
 * its id. Null when the row is unknown.
 */
function friendshipActionUrl(
  friendshipAction: FriendshipAction,
  userId: number,
  friendshipId: null | number,
): null | string {
  if (friendshipAction === "send") {
    return store.url(userId);
  }

  if (friendshipId === null) {
    return null;
  }

  switch (friendshipAction) {
    case "accept":
      return accept.url(friendshipId);
    case "cancel":
      return cancel.url(friendshipId);
    case "decline":
      return decline.url(friendshipId);
    case "remove":
      return destroy.url(friendshipId);
  }
}

/**
 * The state shown while the action is in flight, before the server answers.
 */
function optimisticFriendshipState(friendshipAction: FriendshipAction): FriendshipState {
  switch (friendshipAction) {
    case "accept":
      return "friends";
    case "send":
      return "request_sent";
    case "cancel":
    case "decline":
    case "remove":
      return "none";
  }
}

function processingLabelKey(friendshipAction: FriendshipAction): string {
  switch (friendshipAction) {
    case "accept":
      return "friendship/button.processing.accepting";
    case "cancel":
      return "friendship/button.processing.canceling";
    case "decline":
      return "friendship/button.processing.declining";
    case "remove":
      return "friendship/button.processing.removing";
    case "send":
      return "friendship/button.processing.sending";
  }
}

/**
 * Read a refusal's body, which carries the stored state and the message; null for any other body
 * (a 429 from the throttle, an HTML error page).
 */
function parseFriendshipActionResponse(responseBody: string): FriendshipActionResponse | null {
  try {
    const parsedBody: unknown = JSON.parse(responseBody);

    if (
      typeof parsedBody === "object" &&
      parsedBody !== null &&
      "message" in parsedBody &&
      "state" in parsedBody &&
      "friendshipId" in parsedBody &&
      typeof parsedBody.message === "string" &&
      typeof parsedBody.state === "string" &&
      (parsedBody.friendshipId === null || typeof parsedBody.friendshipId === "number")
    ) {
      return parsedBody as FriendshipActionResponse;
    }
  } catch {
    return null;
  }

  return null;
}

export default FriendshipButton;
